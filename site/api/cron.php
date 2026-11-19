<?php
/* Avvisi di scadenza: da chiamare ogni ora dal cron dell'hosting, con la chiave segreta
   (la trovi in Impostazioni). Funziona anche da riga di comando: php api/cron.php */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
    if (!hash_equals(chiave_cron(), (string)($_GET['k'] ?? ''))) { http_response_code(403); exit("Chiave non valida.\n"); }
}
Migrazioni::esegui(db());
$r = Avvisi::esegui();
$pul = random_int(1, 24) === 1 ? Documenti::pulisci() : 0;
echo date('Y-m-d H:i') . " avvisi inviati {$r['inviati']}, errori {$r['errori']}, saltati {$r['saltati']}" . ($pul ? ", file orfani rimossi $pul" : '') . "\n";
