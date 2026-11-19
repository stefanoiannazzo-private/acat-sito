<?php
declare(strict_types=1);

/** Testo sicuro in HTML */
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Importo in euro all'italiana */
function euro(float|string|null $v): string { return $v === null || $v === '' ? '–' : '€ ' . number_format((float)$v, 2, ',', '.'); }

/** Numero decimale scritto all'italiana o all'inglese ("1,25" o "1.25") */
function num(string|float|int|null $v): ?float {
    if ($v === null) return null;
    $v = trim(str_replace([' ', '€', '%'], '', (string)$v));
    if ($v === '') return null;
    if (str_contains($v, ',')) $v = str_replace(['.', ','], ['', '.'], $v);
    elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $v)) $v = str_replace('.', '', $v); // 100.000 = centomila
    return is_numeric($v) ? (float)$v : null;
}

function client_ip_hash(): string {
    $salt = config()['salt'] ?? 'acat';
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $salt);
}

function impostazione(string $k, ?string $default = null): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try { foreach (db()->query('SELECT chiave, valore FROM impostazioni') as $r) $cache[$r['chiave']] = $r['valore']; } catch (Throwable) {}
    }
    return $cache[$k] ?? $default;
}

function json_out(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function eta_da_data(string $nascita, ?DateTimeImmutable $oggi = null): ?int {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $nascita);
    if (!$d || $d->format('Y-m-d') !== $nascita) return null;
    $oggi ??= new DateTimeImmutable('today');
    if ($d > $oggi) return null;
    return $d->diff($oggi)->y;
}

/** Data e ora UTC del database mostrate all'ora italiana */
function data_it(?string $utc, bool $ora = true): string {
    if (!$utc) return '–';
    $d = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $d->setTimezone(new DateTimeZone('Europe/Rome'))->format($ora ? 'd/m/Y H:i' : 'd/m/Y');
}

/** Chiave segreta per chiamare il cron degli avvisi */
function chiave_cron(): string {
    return substr(hash_hmac('sha256', 'cron-avvisi', (string)(config()['salt'] ?? '')), 0, 32);
}
