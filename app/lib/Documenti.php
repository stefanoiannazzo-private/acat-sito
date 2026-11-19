<?php
declare(strict_types=1);

/**
 * Archivio dei documenti dei clienti. I file stanno nella cartella "archivio" del sito, chiusa al web
 * (.htaccess), con nomi casuali; si scaricano solo dal pannello dopo l'accesso.
 */
final class Documenti
{
    /** estensione => tipi MIME accettati (controllati sul contenuto del file, non sul nome) */
    public const TIPI = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
    ];

    public static function cartella(): string
    {
        $d = dirname(APP_DIR) . '/archivio';
        if (!is_dir($d)) {
            if (!@mkdir($d, 0750, true) && !is_dir($d)) throw new RuntimeException('Impossibile creare la cartella dei documenti sul server.');
        }
        if (!is_file("$d/.htaccess")) file_put_contents("$d/.htaccess", "# Documenti dei clienti: mai accessibili dal web\nRequire all denied\n");
        if (!is_file("$d/index.html")) file_put_contents("$d/index.html", '');
        return $d;
    }

    public static function maxByte(): int
    {
        $mb = (float)(impostazione('documenti_max_mb', '10') ?: 10);
        return (int)min($mb * 1024 * 1024, self::limiteServer());
    }

    /** Limite imposto dalla configurazione PHP dell'hosting */
    public static function limiteServer(): int
    {
        $b = fn($v) => (int)$v * match (strtolower(substr(trim((string)$v), -1))) { 'g' => 1 << 30, 'm' => 1 << 20, 'k' => 1 << 10, default => 1 };
        return min($b(ini_get('upload_max_filesize') ?: '2M'), $b(ini_get('post_max_size') ?: '8M'));
    }

    /**
     * Controlla e salva un file caricato. @return array{0:?int,1:?string} [id documento, errore]
     */
    public static function salva(array $f, int $clienteId, ?int $polizzaId, ?string $descrizione, ?string $utente): array
    {
        $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return [null, 'Il file è troppo grande.'];
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'] ?? '')) return [null, 'Nessun file ricevuto: riprova.'];
        $nome = trim(preg_replace('/[\x00-\x1f\/\\\\]+/', '_', (string)$f['name']));
        $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        if (!isset(self::TIPI[$ext])) return [null, 'Formato non ammesso: carica PDF, Word (doc, docx), JPG o PNG.'];
        $size = (int)filesize($f['tmp_name']);
        if ($size <= 0) return [null, 'Il file è vuoto.'];
        if ($size > self::maxByte()) return [null, 'Il file supera il limite di ' . self::mb(self::maxByte()) . '.'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: 'application/octet-stream';
        if (!in_array($mime, self::TIPI[$ext], true)) return [null, "Il contenuto del file non corrisponde a un .$ext valido."];
        $file = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], self::cartella() . '/' . $file)) return [null, 'Salvataggio del file non riuscito sul server.'];
        @chmod(self::cartella() . '/' . $file, 0640);
        db()->prepare('INSERT INTO documenti (cliente_id, polizza_id, nome, file, mime, dimensione, descrizione, caricato_da, creato_il) VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())')
            ->execute([$clienteId, $polizzaId, mb_substr($nome, 0, 190), $file, $mime, $size, $descrizione ? mb_substr($descrizione, 0, 190) : null, $utente]);
        return [(int)db()->lastInsertId(), null];
    }

    public static function trova(int $id): ?array
    {
        $st = db()->prepare('SELECT * FROM documenti WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function percorso(array $d): string
    {
        return self::cartella() . '/' . basename($d['file']);
    }

    public static function elimina(int $id): void
    {
        if ($d = self::trova($id)) {
            @unlink(self::percorso($d));
            db()->prepare('DELETE FROM documenti WHERE id = ?')->execute([$id]);
        }
    }

    /** Toglie dal disco i file dei documenti già cancellati dal database (es. cliente eliminato) */
    public static function pulisci(): int
    {
        $noti = array_flip(db()->query('SELECT file FROM documenti')->fetchAll(PDO::FETCH_COLUMN));
        $n = 0;
        foreach (glob(self::cartella() . '/*.*') ?: [] as $f) {
            $b = basename($f);
            if (preg_match('/^[a-f0-9]{32}\.[a-z]+$/', $b) && !isset($noti[$b]) && filemtime($f) < time() - 3600) { @unlink($f); $n++; }
        }
        return $n;
    }

    public static function mb(int $b): string
    {
        return $b >= 1048576 ? str_replace('.', ',', (string)round($b / 1048576, 1)) . ' MB' : max(1, (int)round($b / 1024)) . ' KB';
    }
}
