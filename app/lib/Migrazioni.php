<?php
declare(strict_types=1);

/** Applica in ordine i file di app/sql (NNN_nome.sql o .php) non ancora eseguiti. */
final class Migrazioni
{
    public static function esegui(PDO $db): array
    {
        $db->exec('CREATE TABLE IF NOT EXISTS schema_version (versione INT PRIMARY KEY, nome VARCHAR(120) NOT NULL, applicata_il DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $fatte = array_map('intval', $db->query('SELECT versione FROM schema_version')->fetchAll(PDO::FETCH_COLUMN));
        $file = array_merge(glob(APP_DIR . '/sql/[0-9][0-9][0-9]_*.sql') ?: [], glob(APP_DIR . '/sql/[0-9][0-9][0-9]_*.php') ?: []);
        sort($file);
        $applicate = [];
        if (!array_diff(array_map(fn($f) => (int)substr(basename($f), 0, 3), $file), $fatte)) return [];
        $lock = (int)$db->query("SELECT GET_LOCK('acat_migrazioni', 30)")->fetchColumn();
        if ($lock !== 1) throw new RuntimeException('Aggiornamento del database già in corso, riprova tra poco.');
        try {
            $fatte = array_map('intval', $db->query('SELECT versione FROM schema_version')->fetchAll(PDO::FETCH_COLUMN));
            foreach ($file as $f) {
                $v = (int)substr(basename($f), 0, 3);
                if (in_array($v, $fatte, true)) continue;
                if (str_ends_with($f, '.sql')) {
                    foreach (self::istruzioni((string)file_get_contents($f)) as $q) $db->exec($q);
                } else {
                    (require $f)($db);
                }
                $st = $db->prepare('INSERT INTO schema_version (versione, nome, applicata_il) VALUES (?, ?, UTC_TIMESTAMP())');
                $st->execute([$v, basename($f)]);
                $applicate[] = basename($f);
            }
        } finally {
            $db->query("SELECT RELEASE_LOCK('acat_migrazioni')");
        }
        return $applicate;
    }

    /** Divide un file SQL nelle singole istruzioni (separate da ; a fine riga), togliendo i commenti -- */
    public static function istruzioni(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        return array_values(array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)), fn($s) => $s !== ''));
    }
}
