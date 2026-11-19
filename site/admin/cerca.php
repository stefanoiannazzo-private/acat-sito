<?php
/* Ricerche veloci per i moduli del pannello (comuni e modelli), solo per utenti collegati. */
declare(strict_types=1);
require __DIR__ . '/_init.php';
Auth::richiedi();
if (($_GET['cosa'] ?? '') === 'comuni') {
    $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 60));
    $st = db()->prepare('SELECT id, CONCAT(nome, \' (\', sigla, \')\') AS n FROM comuni WHERE nome LIKE ? ORDER BY nome LIMIT 20');
    $st->execute([str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%']);
    json_out(['ok' => true, 'r' => $st->fetchAll()]);
}
if (($_GET['cosa'] ?? '') === 'modelli') {
    $st = db()->prepare('SELECT id, nome AS n FROM modelli WHERE marca_id = ? ORDER BY nome');
    $st->execute([(int)($_GET['marca'] ?? 0)]);
    json_out(['ok' => true, 'r' => $st->fetchAll()]);
}
if (($_GET['cosa'] ?? '') === 'prodotti') {
    $st = db()->prepare('SELECT id, nome AS n, categoria_id AS c FROM prodotti WHERE compagnia_id = ? AND attivo = 1 ORDER BY nome');
    $st->execute([(int)($_GET['compagnia'] ?? 0)]);
    json_out(['ok' => true, 'r' => $st->fetchAll()]);
}
if (($_GET['cosa'] ?? '') === 'clienti') {
    $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 60));
    $tipo = ($_GET['tipo'] ?? '') === 'azienda' ? 'azienda' : 'persona';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $st = db()->prepare("SELECT id, CONCAT(nome, IF(codice_fiscale IS NULL AND partita_iva IS NULL, CONCAT(' · n. ', id), CONCAT(' · ', COALESCE(partita_iva, codice_fiscale)))) AS n
        FROM clienti WHERE tipo = ? AND (nome LIKE ? OR email LIKE ? OR codice_fiscale LIKE ? OR partita_iva LIKE ?) ORDER BY nome LIMIT 20");
    $st->execute([$tipo, $like, $like, $like, $like]);
    json_out(['ok' => true, 'r' => $st->fetchAll()]);
}
json_out(['ok' => false], 400);
