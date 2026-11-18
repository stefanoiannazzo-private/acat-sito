<?php
declare(strict_types=1);

/**
 * Invio email. Con la configurazione "smtp" (casella del dominio su Aruba: smtps.aruba.it, porta 465, SSL)
 * si autentica e invia via SMTP: è il modo affidabile. Senza, ripiega sulla funzione mail() dell'hosting.
 */
final class Mailer
{
    public static function configurato(): bool
    {
        $s = config()['smtp'] ?? null;
        return is_array($s) && !empty($s['host']) && !empty($s['user']) && !empty($s['pass']);
    }

    public static function mittente(): string
    {
        $s = config()['smtp'] ?? [];
        if (!empty($s['from'])) return $s['from'];
        if (!empty($s['user']) && filter_var($s['user'], FILTER_VALIDATE_EMAIL)) return $s['user'];
        return 'noreply@' . preg_replace('/^www\./', '', preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
    }

    /**
     * @return string|null null se inviata, altrimenti il motivo dell'errore
     */
    public static function invia(string $a, string $oggetto, string $html, string $testo, ?string $rispondiA = null, ?string $nomeMittente = null): ?string
    {
        if (!filter_var($a, FILTER_VALIDATE_EMAIL)) return 'Indirizzo del destinatario non valido.';
        $from = self::mittente();
        $nome = $nomeMittente ?? (string)impostazione('email_mittente_nome', 'ACAT Servizi');
        $msg = self::componi($from, $nome, $a, $oggetto, $html, $testo, $rispondiA);
        try {
            if (self::configurato()) {
                self::smtp(config()['smtp'], $from, $a, $msg['header'] . "\r\n" . $msg['body']);
            } else {
                $hdr = preg_replace('/^(To|Subject): .*\r\n/m', '', $msg['header']);
                if (!@mail($a, $msg['subject'], $msg['body'], rtrim($hdr), '-f' . $from)) return 'La funzione mail() dell\'hosting non ha accettato il messaggio.';
            }
        } catch (Throwable $e) {
            error_log('[mailer] ' . $e->getMessage());
            return mb_substr($e->getMessage(), 0, 250);
        }
        return null;
    }

    /** Messaggio MIME multipart (testo + HTML), intestazioni con CRLF */
    public static function componi(string $from, string $nome, string $a, string $oggetto, string $html, string $testo, ?string $rispondiA): array
    {
        $clean = fn($s) => str_replace(["\r", "\n"], '', (string)$s);
        $b = 'acat-' . bin2hex(random_bytes(12));
        $subject = '=?UTF-8?B?' . base64_encode($clean($oggetto)) . '?=';
        $host = substr(strrchr($from, '@') ?: '@localhost', 1);
        $h = [
            'Date: ' . date(DATE_RFC2822),
            'From: =?UTF-8?B?' . base64_encode($clean($nome)) . '?= <' . $clean($from) . '>',
            'To: <' . $clean($a) . '>',
            'Subject: ' . $subject,
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $host . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $b . '"',
        ];
        if ($rispondiA && filter_var($rispondiA, FILTER_VALIDATE_EMAIL)) $h[] = 'Reply-To: <' . $clean($rispondiA) . '>';
        $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($testo))
              . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
              . "--$b--\r\n";
        return ['header' => implode("\r\n", $h) . "\r\n", 'body' => $body, 'subject' => $subject];
    }

    private static function smtp(array $s, string $from, string $to, string $data): void
    {
        $sicurezza = $s['secure'] ?? 'ssl';
        $porta = (int)($s['port'] ?? ($sicurezza === 'ssl' ? 465 : 587));
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $remote = ($sicurezza === 'ssl' ? 'ssl://' : 'tcp://') . $s['host'] . ':' . $porta;
        $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) throw new RuntimeException("Connessione al server di posta non riuscita ($errstr).");
        stream_set_timeout($fp, 30);
        $leggi = function () use ($fp): array {
            $txt = '';
            while (($l = fgets($fp, 1024)) !== false) { $txt .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
            return [(int)substr($txt, 0, 3), trim($txt)];
        };
        $cmd = function (string $c, array $ok) use ($fp, $leggi): string {
            fwrite($fp, $c . "\r\n");
            [$code, $txt] = $leggi();
            if (!in_array($code, $ok, true)) {
                $mostra = str_starts_with($c, 'AUTH') || preg_match('/^[A-Za-z0-9+\/=]+$/', $c) ? '(credenziali)' : strtok($c, ' ');
                throw new RuntimeException("Il server di posta ha rifiutato $mostra: $txt");
            }
            return $txt;
        };
        try {
            [$code, $txt] = $leggi();
            if ($code !== 220) throw new RuntimeException("Risposta inattesa dal server di posta: $txt");
            $io = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
            $cmd("EHLO $io", [250]);
            if ($sicurezza === 'tls') {
                $cmd('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('Connessione cifrata non riuscita.');
                $cmd("EHLO $io", [250]);
            }
            if (!empty($s['user'])) {
                $cmd('AUTH LOGIN', [334]);
                $cmd(base64_encode($s['user']), [334]);
                $cmd(base64_encode($s['pass']), [235]);
            }
            $cmd("MAIL FROM:<$from>", [250]);
            $cmd("RCPT TO:<$to>", [250, 251]);
            $cmd('DATA', [354]);
            $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $data));
            $cmd($data . "\r\n.", [250]);
            fwrite($fp, "QUIT\r\n");
        } finally {
            fclose($fp);
        }
    }

    /** Registra l'invio nel registro delle email */
    public static function registra(?int $clienteId, ?int $preventivoId, string $tipo, string $a, string $oggetto, ?string $errore, ?string $utente): void
    {
        db()->prepare('INSERT INTO email_inviate (cliente_id, preventivo_id, tipo, destinatario, oggetto, esito, errore, utente, creato_il) VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())')
            ->execute([$clienteId, $preventivoId, $tipo, $a, mb_substr($oggetto, 0, 255), $errore === null ? 'inviata' : 'errore', $errore, $utente]);
    }
}
