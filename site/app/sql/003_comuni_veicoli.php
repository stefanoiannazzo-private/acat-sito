<?php
/* Carica l'elenco ISTAT dei comuni e l'elenco iniziale di marche e modelli (app/data). */
return function (PDO $db): void {
    $f = fopen(APP_DIR . '/data/comuni.csv', 'r');
    fgetcsv($f, null, ',', '"', '');
    $st = $db->prepare('INSERT IGNORE INTO comuni (codice_istat, nome, sigla, provincia, regione, cap) VALUES (?, ?, ?, ?, ?, ?)');
    $db->beginTransaction();
    while (($r = fgetcsv($f, null, ',', '"', '')) !== false) {
        if (count($r) < 6) continue;
        $st->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[5] !== '' ? $r[5] : null]);
    }
    $db->commit();
    fclose($f);

    $f = fopen(APP_DIR . '/data/veicoli.csv', 'r');
    fgetcsv($f, null, ',', '"', '');
    $insM = $db->prepare('INSERT INTO marche (nome, tipo_veicolo, attivo) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    $insMod = $db->prepare('INSERT IGNORE INTO modelli (marca_id, nome, anno_inizio, anno_fine) VALUES (?, ?, ?, ?)');
    $ids = [];
    $db->beginTransaction();
    while (($r = fgetcsv($f, null, ',', '"', '')) !== false) {
        if (count($r) < 6) continue;
        [$tipo, $marca, $attiva, $modello, $a1, $a2] = $r;
        $k = $tipo . '|' . mb_strtolower($marca);
        if (!isset($ids[$k])) {
            $insM->execute([$marca, $tipo, (int)$attiva]);
            $ids[$k] = (int)$db->lastInsertId();
        }
        $insMod->execute([$ids[$k], $modello, $a1 !== '' ? (int)$a1 : null, $a2 !== '' ? (int)$a2 : null]);
    }
    $db->commit();
    fclose($f);
};
