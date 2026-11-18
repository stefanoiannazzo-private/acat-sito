<?php
/* Crea l'anagrafica per le richieste arrivate prima che esistessero i clienti (stessa email = stesso cliente). */
return function (PDO $db): void {
    $nuovo = (int)$db->query("SELECT id FROM stati_cliente WHERE codice = 'nuovo'")->fetchColumn();
    $righe = $db->query('SELECT id, creato_il, nome, email, telefono, data_nascita, comune_id, consenso_privacy FROM preventivi WHERE cliente_id IS NULL ORDER BY id')->fetchAll();
    $ins = $db->prepare('INSERT INTO clienti (nome, email, telefono, data_nascita, comune_id, stato_id, origine, consenso_privacy, creato_il, aggiornato_il) VALUES (?, ?, ?, ?, ?, ?, \'sito\', ?, ?, ?)');
    $link = $db->prepare('UPDATE preventivi SET cliente_id = ? WHERE id = ?');
    $perEmail = [];
    foreach ($righe as $r) {
        $k = mb_strtolower(trim((string)$r['email']));
        if ($k === '' || !isset($perEmail[$k])) {
            $ins->execute([$r['nome'] ?: 'Cliente senza nome', $r['email'], $r['telefono'], $r['data_nascita'], $r['comune_id'], $nuovo, (int)$r['consenso_privacy'], $r['creato_il'], $r['creato_il']]);
            $id = (int)$db->lastInsertId();
            if ($k !== '') $perEmail[$k] = $id;
        } else {
            $id = $perEmail[$k];
        }
        $link->execute([$id, $r['id']]);
    }
};
