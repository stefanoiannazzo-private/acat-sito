<?php
/* Salva la richiesta di preventivo definitivo (dati del calcolo + contatti) e avvisa l'agenzia. */
declare(strict_types=1);
require __DIR__ . '/_init.php';

controlla_attivo();
if (!Limiti::consenti('richiesta', 5)) json_out(['ok' => false, 'errore' => 'Troppe richieste, riprova tra un minuto.'], 429);
$d = input_json();
if (!empty($d['sito_web'])) json_out(['ok' => true, 'stima' => null]); // campo trappola per i robot

[$comune, $nascita, $modello, $garanzie] = dati_calcolo($d);
[$c, $err] = dati_contatto($d);
if ($err) json_out(['ok' => false, 'errore' => $err], 422);
[$r, $err] = Preventivatore::calcola($comune, $nascita, $modello, $garanzie);
if ($err) json_out(['ok' => false, 'errore' => $err], 422);

$nomiGaranzie = implode(', ', array_map(fn($x) => $x['nome'], $r['righe']));
$st = db()->prepare('INSERT INTO preventivi (creato_il, comune_id, comune_nome, data_nascita, eta, modello_id, veicolo, garanzie, dettaglio, totale, nome, email, telefono, note, consenso_privacy, ip_hash)
    VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
$st->execute([$comune, $r['comune'], $nascita, $r['eta'], $modello, $r['veicolo'], $nomiGaranzie, json_encode($r, JSON_UNESCAPED_UNICODE), $r['totale'],
    $c['nome'], $c['email'], $c['telefono'] ?: null, $c['note'] ?: null, client_ip_hash()]);
$id = (int)db()->lastInsertId();

$to = trim((string)impostazione('email_notifiche', ''));
if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
    $host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $righe = implode("\n", array_map(fn($x) => '- ' . $x['nome'] . ': ' . ($x['importo'] === null ? 'su richiesta' : number_format((float)$x['importo'], 2, ',', '.') . ' €'), $r['righe']));
    $testo = "Nuova richiesta di preventivo RC auto n. $id\n\n"
        . "Nome: {$c['nome']}\nEmail: {$c['email']}\nTelefono: " . ($c['telefono'] ?: '-') . "\n"
        . "Comune: {$r['comune']}\nEtà: {$r['eta']} anni (nato il $nascita)\nVeicolo: {$r['veicolo']}\n\nGaranzie:\n$righe\n"
        . 'Totale stimato: ' . number_format((float)$r['totale'], 2, ',', '.') . " €\n\nNote: " . ($c['note'] ?: '-') . "\n\n"
        . "Dettagli nel pannello: https://$host/admin/preventivi.php?id=$id\n";
    $oggetto = '=?UTF-8?B?' . base64_encode("Preventivo RC auto n. $id - {$c['nome']}") . '?=';
    $hdr = "From: Sito ACAT Servizi <noreply@$host>\r\nReply-To: " . str_replace(["\r", "\n"], '', $c['email']) . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
    @mail($to, $oggetto, $testo, $hdr);
}
json_out(['ok' => true, 'id' => $id, 'stima' => stima_pubblica($r)]);
