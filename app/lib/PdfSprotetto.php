<?php
declare(strict_types=1);

/**
 * Toglie la protezione "standard" ai PDF che si aprono senza password ma sono cifrati
 * (tipico dei documenti scaricati dai portali delle compagnie): RC4 40/128 bit, AES-128 e AES-256.
 * Ricostruisce un PDF equivalente con i flussi decifrati, così la libreria di lettura ne estrae il testo.
 * Se il file richiede una password per essere aperto, non fa nulla.
 */
final class PdfSprotetto
{
    private const PAD = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

    /** @return string|null PDF decifrato, oppure null se non è cifrato o non si può decifrare */
    public static function decifra(string $pdf): ?string
    {
        if (!preg_match('/\/Encrypt\s+(\d+)\s+(\d+)\s+R/', $pdf, $m)) return null;
        $encNum = (int)$m[1];
        $oggetti = self::oggetti($pdf);
        if (!isset($oggetti[$encNum])) return null;
        $enc = $oggetti[$encNum]['dict'];
        $id0 = preg_match('/\/ID\s*\[\s*<([0-9A-Fa-f]*)>/', $pdf, $m) ? hex2bin(strlen($m[1]) % 2 ? $m[1] . '0' : $m[1]) : '';
        $v = self::num($enc, 'V'); $r = self::num($enc, 'R');
        // con V=4 la chiave è sempre di 128 bit (il /Length dentro /CF è in byte e confonderebbe)
        $len = $v >= 4 ? 16 : (int)((self::num(preg_replace('/\/CF\s*<<.*>>\s*>>/s', '', $enc), 'Length') ?: 40) / 8);
        $o = self::stringa($enc, 'O'); $u = self::stringa($enc, 'U'); $p = (int)self::num($enc, 'P');
        $aes = (bool)preg_match('/\/CFM\s*\/AESV[23]/', $enc);
        $metadati = !preg_match('/\/EncryptMetadata\s+false/', $enc);
        if ($o === null || $u === null) return null;

        if ($r >= 5) {
            $oe = self::stringa($enc, 'UE');
            if ($oe === null) return null;
            $salt = substr($u, 40, 8);
            $hash = $r === 5 ? hash('sha256', $salt, true) : self::hash2B('', $salt, '');
            $chiave = openssl_decrypt($oe, 'aes-256-cbc', $hash, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 16));
            if ($chiave === false) return null;
            $chiave = substr($chiave, 0, 32);
            $aes = true; $aes256 = true;
        } else {
            if ($r === 2) $len = 5;
            $h = md5(self::PAD . $o . pack('V', $p) . $id0 . ($r >= 4 && !$metadati ? "\xFF\xFF\xFF\xFF" : ''), true);
            if ($r >= 3) for ($i = 0; $i < 50; $i++) $h = md5(substr($h, 0, $len), true);
            $chiave = substr($h, 0, $len);
            // verifica: la password utente vuota deve essere giusta
            $ok = $r === 2 ? self::rc4($chiave, self::PAD) === $u
                : substr(self::verificaU3($chiave, $id0), 0, 16) === substr($u, 0, 16);
            if (!$ok) return null;
            $aes256 = false;
        }

        // ricostruisce il file: intestazione, oggetti decifrati, nuova tabella xref, trailer senza /Encrypt
        $out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $off = [];
        $max = 0;
        foreach ($oggetti as $num => $ob) {
            if ($num === $encNum) continue;
            $max = max($max, $num);
            $dict = $ob['dict'];
            $corpo = $dict;
            if ($ob['stream'] !== null) {
                $dati = $ob['stream'];
                if (!preg_match('/\/Type\s*\/XRef\b/', $dict) && !($ob['metadata'] && !$metadati)) {
                    $k = $aes256 ? $chiave : self::chiaveOggetto($chiave, $num, $ob['gen'], $aes);
                    $dati = $aes ? self::aes($k, $dati) : self::rc4($k, $dati);
                }
                $dict = preg_replace('/\/Length\s+\d+(\s+\d+\s+R)?/', '/Length ' . strlen($dati), $dict, 1);
                $corpo = $dict . "\nstream\n" . $dati . "\nendstream";
            }
            $off[$num] = strlen($out);
            $out .= $num . ' ' . $ob['gen'] . " obj\n" . $corpo . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) $out .= isset($off[$i]) ? sprintf("%010d %05d n \n", $off[$i], $oggetti[$i]['gen']) : "0000000000 65535 f \n";
        $root = preg_match('/\/Root\s+(\d+\s+\d+\s+R)/', $pdf, $m) ? $m[1] : null;
        if (!$root) return null;
        $info = preg_match('/\/Info\s+(\d+\s+\d+\s+R)/', $pdf, $m) ? ' /Info ' . $m[1] : '';
        $out .= "trailer\n<< /Size " . ($max + 1) . " /Root $root$info >>\nstartxref\n$xref\n%%EOF\n";
        return $out;
    }

    /** Oggetti indiretti: numero => [gen, dict (testo prima di "stream"), stream (byte) o null] */
    private static function oggetti(string $pdf): array
    {
        $out = [];
        if (!preg_match_all('/(?<![0-9])(\d+)\s+(\d+)\s+obj\b/', $pdf, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) return $out;
        foreach ($m as $x) {
            $num = (int)$x[1][0]; $gen = (int)$x[2][0];
            $start = $x[0][1] + strlen($x[0][0]);
            $fine = strpos($pdf, 'endobj', $start);
            $sPos = strpos($pdf, 'stream', $start);
            $stream = null;
            if ($sPos !== false && ($fine === false || $sPos < $fine)) {
                $dict = trim(substr($pdf, $start, $sPos - $start));
                $dataStart = $sPos + 6;
                if (($pdf[$dataStart] ?? '') === "\r") $dataStart++;
                if (($pdf[$dataStart] ?? '') === "\n") $dataStart++;
                $lunghezza = null;
                if (preg_match('/\/Length\s+(\d+)(\s+(\d+)\s+R)?/', $dict, $lm)) {
                    if (empty($lm[2])) $lunghezza = (int)$lm[1];
                    elseif (preg_match('/(?<![0-9])' . (int)$lm[1] . '\s+\d+\s+obj\s*(\d+)\s*endobj/', $pdf, $rm)) $lunghezza = (int)$rm[1];
                }
                if ($lunghezza !== null && substr($pdf, $dataStart + $lunghezza, 30) !== '' && preg_match('/^\s*endstream/', substr($pdf, $dataStart + $lunghezza, 30))) {
                    $stream = substr($pdf, $dataStart, $lunghezza);
                    $fine = strpos($pdf, 'endobj', $dataStart + $lunghezza);
                } else {
                    $es = strpos($pdf, 'endstream', $dataStart);
                    if ($es === false) continue;
                    $stream = rtrim(substr($pdf, $dataStart, $es - $dataStart), "\r\n");
                    $fine = strpos($pdf, 'endobj', $es);
                }
            } else {
                $dict = trim(substr($pdf, $start, ($fine === false ? strlen($pdf) : $fine) - $start));
            }
            // con aggiornamenti incrementali vale l'ultima versione dell'oggetto
            $out[$num] = ['gen' => $gen, 'dict' => $dict, 'stream' => $stream, 'metadata' => (bool)preg_match('/\/Type\s*\/Metadata\b/', $dict)];
        }
        ksort($out);
        return $out;
    }

    private static function num(string $d, string $k): ?int
    {
        return preg_match('/\/' . $k . '\s+(-?\d+)/', $d, $m) ? (int)$m[1] : null;
    }

    /** Stringa letterale (...) o esadecimale <...> associata a una chiave del dizionario */
    private static function stringa(string $d, string $k): ?string
    {
        if (!preg_match('/\/' . $k . '\s*([(<])/', $d, $m, PREG_OFFSET_CAPTURE)) return null;
        $i = $m[1][1];
        if ($m[1][0] === '<') {
            $e = strpos($d, '>', $i);
            $h = preg_replace('/\s+/', '', substr($d, $i + 1, $e - $i - 1));
            return hex2bin(strlen($h) % 2 ? $h . '0' : $h) ?: '';
        }
        $out = ''; $liv = 0; $n = strlen($d);
        for ($j = $i + 1; $j < $n; $j++) {
            $c = $d[$j];
            if ($c === '\\') {
                $c2 = $d[++$j] ?? '';
                if (ctype_digit($c2) && $c2 < '8') {
                    $oct = $c2;
                    for ($t = 0; $t < 2 && isset($d[$j + 1]) && $d[$j + 1] >= '0' && $d[$j + 1] <= '7'; $t++) $oct .= $d[++$j];
                    $out .= chr(octdec($oct) & 255);
                } else {
                    $out .= match ($c2) { 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C", "\r", "\n" => '', default => $c2 };
                    if ($c2 === "\r" && ($d[$j + 1] ?? '') === "\n") $j++;
                }
                continue;
            }
            if ($c === '(') $liv++;
            if ($c === ')') { if ($liv === 0) break; $liv--; }
            $out .= $c;
        }
        return $out;
    }

    private static function verificaU3(string $chiave, string $id0): string
    {
        $x = self::rc4($chiave, md5(self::PAD . $id0, true));
        for ($i = 1; $i <= 19; $i++) {
            $k = ''; for ($j = 0; $j < strlen($chiave); $j++) $k .= chr(ord($chiave[$j]) ^ $i);
            $x = self::rc4($k, $x);
        }
        return $x;
    }

    private static function chiaveOggetto(string $chiave, int $num, int $gen, bool $aes): string
    {
        $k = md5($chiave . substr(pack('V', $num), 0, 3) . substr(pack('V', $gen), 0, 2) . ($aes ? 'sAlT' : ''), true);
        return substr($k, 0, min(strlen($chiave) + 5, 16));
    }

    private static function aes(string $k, string $dati): string
    {
        if (strlen($dati) < 32) return '';
        $alg = strlen($k) === 32 ? 'aes-256-cbc' : 'aes-128-cbc';
        $r = openssl_decrypt(substr($dati, 16), $alg, $k, OPENSSL_RAW_DATA, substr($dati, 0, 16));
        return $r === false ? '' : $r;
    }

    public static function rc4(string $k, string $dati): string
    {
        $s = range(0, 255); $j = 0; $kl = strlen($k);
        for ($i = 0; $i < 256; $i++) { $j = ($j + $s[$i] + ord($k[$i % $kl])) & 255; [$s[$i], $s[$j]] = [$s[$j], $s[$i]]; }
        $i = $j = 0; $out = '';
        $n = strlen($dati);
        for ($y = 0; $y < $n; $y++) {
            $i = ($i + 1) & 255; $j = ($j + $s[$i]) & 255; [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
            $out .= chr(ord($dati[$y]) ^ $s[($s[$i] + $s[$j]) & 255]);
        }
        return $out;
    }

    /** Algoritmo 2.B (PDF 2.0, revisione 6) */
    private static function hash2B(string $pwd, string $salt, string $udata): string
    {
        $k = hash('sha256', $pwd . $salt . $udata, true);
        for ($i = 0; ; $i++) {
            $k1 = str_repeat($pwd . $k . $udata, 64);
            $e = openssl_encrypt($k1, 'aes-128-cbc', substr($k, 0, 16), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, substr($k, 16, 16));
            $mod = 0; for ($b = 0; $b < 16; $b++) $mod += ord($e[$b]);
            $k = hash(['sha256', 'sha384', 'sha512'][$mod % 3], $e, true);
            if ($i >= 63 && ord($e[strlen($e) - 1]) <= $i - 32) break;
        }
        return substr($k, 0, 32);
    }
}
