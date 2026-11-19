<?php
declare(strict_types=1);

/**
 * Legge un contratto caricato (PDF digitale o Word .docx) e propone i dati della polizza:
 * date di effetto/stipula e scadenza, frazionamento, numero, compagnia, premio, targa.
 * È un aiuto alla compilazione: l'operatore controlla sempre i valori prima di salvare.
 * Le scansioni e le foto non contengono testo leggibile e non vengono interpretate.
 */
final class LettoreContratti
{
    private const MESI = ['gennaio' => 1, 'febbraio' => 2, 'marzo' => 3, 'aprile' => 4, 'maggio' => 5, 'giugno' => 6, 'luglio' => 7,
        'agosto' => 8, 'settembre' => 9, 'ottobre' => 10, 'novembre' => 11, 'dicembre' => 12];

    /** Testo del documento ('' se non leggibile) */
    public static function testo(string $percorso, string $mime): string
    {
        try {
            if ($mime === 'application/pdf' && class_exists(\Smalot\PdfParser\Parser::class)) {
                if (filesize($percorso) > 15 * 1048576) return '';
                $cfg = new \Smalot\PdfParser\Config();
                $cfg->setRetainImageContent(false);
                $t = (new \Smalot\PdfParser\Parser([], $cfg))->parseFile($percorso)->getText();
                return self::pulisci($t);
            }
            if (str_ends_with(strtolower($percorso), '.docx') && class_exists(ZipArchive::class)) {
                $z = new ZipArchive();
                if ($z->open($percorso) !== true) return '';
                $xml = (string)$z->getFromName('word/document.xml');
                $z->close();
                $xml = preg_replace(['/<\/w:p>/', '/<w:tab\/>/', '/<w:br\/>/'], ["\n", ' ', "\n"], $xml);
                return self::pulisci(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            }
        } catch (Throwable $e) {
            error_log('[lettore] ' . $e->getMessage());
        }
        return '';
    }

    private static function pulisci(string $t): string
    {
        $t = str_replace(["\r", "\t", "\u{00A0}"], ["\n", ' ', ' '], $t);
        $t = preg_replace('/[ ]{2,}/', ' ', $t);
        return trim(preg_replace('/\n{3,}/', "\n\n", $t));
    }

    /** Tutte le date nel testo con la loro posizione */
    public static function date(string $t): array
    {
        $out = [];
        if (preg_match_all('/(?<!\d)(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4}|\d{2})(?!\d)/u', $t, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $y = (int)$x[3][0]; if ($y < 100) $y += 2000;
                if ($d = self::valida($y, (int)$x[2][0], (int)$x[1][0])) $out[] = ['data' => $d, 'pos' => $x[0][1], 'len' => strlen($x[0][0])];
            }
        }
        if (preg_match_all('/(?<!\d)(\d{1,2})\s*(' . implode('|', array_keys(self::MESI)) . ')\s*(\d{4})(?!\d)/iu', $t, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($m as $x) {
                if ($d = self::valida((int)$x[3][0], self::MESI[mb_strtolower($x[2][0])], (int)$x[1][0])) $out[] = ['data' => $d, 'pos' => $x[0][1], 'len' => strlen($x[0][0])];
            }
        }
        usort($out, fn($a, $b) => $a['pos'] <=> $b['pos']);
        return $out;
    }

    private static function valida(int $y, int $m, int $d): ?string
    {
        return $y >= 1990 && $y <= 2100 && checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
    }

    /**
     * @param array $compagnie id => nome
     * @return array{trovati: array, note: array}
     */
    public static function analizza(string $t, array $compagnie = []): array
    {
        $r = []; $note = [];
        if (mb_strlen($t) < 40) return ['trovati' => [], 'note' => ['Nel documento non c\'è testo leggibile (forse è una scansione o una foto): inserisci i dati a mano.']];
        $low = mb_strtolower($t);

        // 1) formula tipica: "dalle ore 24 del 02/01/2026 alle ore 24 del 02/01/2027"
        $dataRe = '(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4}|\d{1,2}\s+[a-z]+\s+\d{4})';
        if (preg_match('/dalle\s+ore\s+\d{1,2}(?:[:.]\d{2})?\s+del\s+' . $dataRe . '.{0,40}?alle\s+ore\s+\d{1,2}(?:[:.]\d{2})?\s+del\s+' . $dataRe . '/isu', $t, $m)) {
            $a = self::date($m[1]); $b = self::date($m[2]);
            if ($a && $b) { $r['data_stipula'] = $a[0]['data']; $r['scadenza'] = $b[0]['data']; $note[] = 'Periodo di copertura trovato ("dalle ore … alle ore …").'; }
        }

        // 2) parole chiave prima di ogni data
        $tutte = self::date($t);
        $punti = ['scadenza' => [], 'effetto' => [], 'emissione' => []];
        foreach ($tutte as $d) {
            $prima = mb_strtolower(substr($t, max(0, $d['pos'] - 90), min(90, $d['pos'])));
            $ultima = mb_substr($prima, -45);
            $kw = [
                'scadenza' => '/scadenz|scade\b|scadra|termine|fino al|cessa|alle ore\s+\d+[^a-z]{0,6}del\s*$/u',
                'effetto' => '/effetto|decorrenz|dalle ore|inizio|validit[aà] dal|copertura dal/u',
                'emissione' => '/emess|emission|stipul|sottoscri|firmat|luogo e data|redatt/u',
            ];
            foreach ($kw as $k => $re) {
                if (preg_match($re, $ultima)) $punti[$k][] = ['data' => $d['data'], 'p' => 2, 'pos' => $d['pos']];
                elseif (preg_match($re, $prima)) $punti[$k][] = ['data' => $d['data'], 'p' => 1, 'pos' => $d['pos']];
            }
        }
        $migliore = function (array $c) { usort($c, fn($a, $b) => [$b['p'], $a['pos']] <=> [$a['p'], $b['pos']]); return $c[0]['data'] ?? null; };
        if (!isset($r['data_stipula'])) {
            $eff = $migliore($punti['effetto']) ?? $migliore($punti['emissione']);
            if ($eff) $r['data_stipula'] = $eff;
        }
        if (!isset($r['scadenza'])) {
            $cand = array_values(array_filter($punti['scadenza'], fn($x) => !isset($r['data_stipula']) || $x['data'] > $r['data_stipula']));
            if ($s = $migliore($cand)) $r['scadenza'] = $s;
            elseif (isset($r['data_stipula'])) { $r['scadenza'] = Scadenze::scadenzaDaStipula($r['data_stipula']); $note[] = 'Scadenza non scritta nel documento: proposta un anno dopo la data di effetto.'; }
        }

        // 3) frazionamento
        $fraz = ['annual' => 'annuale', 'semestral' => 'semestrale', 'trimestral' => 'trimestrale', 'mensil' => 'mensile'];
        if (preg_match('/(?:frazionament\w*|rateazion\w*|periodicit\w*|pagament\w*|rate)\W{0,5}(?:[a-z]+\W{1,3}){0,4}?(annual|semestral|trimestral|mensil)/iu', $low, $m)) $r['frazionamento'] = $fraz[$m[1]];
        else {
            $conta = [];
            foreach ($fraz as $k => $v) $conta[$v] = preg_match_all('/\b' . $k . '[ei]\b/u', $low);
            arsort($conta);
            if (reset($conta) > 0) $r['frazionamento'] = key($conta);
        }

        // 4) numero di polizza
        if (preg_match('/polizza\s*(?:n\.?\s*°?|n°|nr\.?|numero|num\.?)\s*[:\-]?\s*([A-Z0-9][A-Z0-9\/.\-]{4,30})/iu', $t, $m)) $r['numero'] = rtrim($m[1], '.-/');

        // 5) compagnia
        $best = null; $max = 0;
        foreach ($compagnie as $id => $nome) {
            $chiave = mb_strtolower(preg_replace('/\s+(assicurazioni|s\.p\.a\.?|spa)$/i', '', (string)$nome));
            if ($chiave === '' || mb_strlen($chiave) < 3) continue;
            $n = preg_match_all('/\b' . preg_quote($chiave, '/') . '\b/u', $low);
            if ($n > $max) { $max = $n; $best = (int)$id; }
        }
        if ($best) $r['compagnia_id'] = $best;

        // 6) premio
        if (preg_match_all('/premio[^0-9€\n]{0,50}(?:€|eur(?:o)?)?\s*([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2}|[0-9]+,[0-9]{2})/iu', $t, $m, PREG_SET_ORDER)) {
            $scelto = $m[0][1];
            foreach ($m as $x) if (preg_match('/annu|total|lordo|complessiv/i', $x[0])) { $scelto = $x[1]; break; }
            $r['premio_annuo'] = num($scelto);
        }

        // 7) targa
        if (preg_match('/\b([A-Z]{2})\s?(\d{3})\s?([A-Z]{2})\b/u', $t, $m) && !preg_match('/^(IT|EU)$/', $m[1])) $r['oggetto'] = 'Targa ' . $m[1] . $m[2] . $m[3];

        if (!isset($r['scadenza'])) $note[] = 'Non ho trovato la scadenza: inseriscila a mano.';
        return ['trovati' => array_filter($r, fn($v) => $v !== null && $v !== ''), 'note' => $note];
    }
}
