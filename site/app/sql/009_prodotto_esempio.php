<?php
/* Le garanzie che prima erano uniche passano a un prodotto "di esempio" (con i valori già impostati),
   così il preventivatore continua a funzionare. Va rinominato o duplicato per le compagnie reali. */
return function (PDO $db): void {
    if (!(int)$db->query('SELECT COUNT(*) FROM garanzie WHERE prodotto_id IS NULL')->fetchColumn()) return;
    $comp = (int)$db->query("SELECT id FROM compagnie ORDER BY (nome = 'Allianz') DESC, id LIMIT 1")->fetchColumn();
    $cat = (int)$db->query("SELECT id FROM categorie WHERE chiave = 'rc_auto'")->fetchColumn() ?: null;
    $db->prepare("INSERT INTO prodotti (compagnia_id, categoria_id, nome, descrizione, preventivabile, veicoli) VALUES (?, ?, 'Tariffa RC auto di esempio', 'Listino di partenza: rinominalo o duplicalo per ogni compagnia e inserisci i prezzi reali', 1, 'entrambi')
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)")->execute([$comp, $cat]);
    $pid = (int)$db->lastInsertId();
    $db->prepare('UPDATE garanzie SET prodotto_id = ? WHERE prodotto_id IS NULL')->execute([$pid]);
    $db->prepare("INSERT IGNORE INTO prodotti_frazionamenti (prodotto_id, frazionamento, variazione) VALUES (?, 'annuale', 0), (?, 'semestrale', 3), (?, 'mensile', 5)")->execute([$pid, $pid, $pid]);
    $db->prepare('INSERT IGNORE INTO prodotti_durate (prodotto_id, anni, sconto) VALUES (?, 1, 0), (?, 2, 5), (?, 3, 8)')->execute([$pid, $pid, $pid]);
};
