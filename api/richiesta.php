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
db()->beginTransaction();
$clienteId = Clienti::trovaOCrea(['nome' => $c['nome'], 'email' => $c['email'], 'telefono' => $c['telefono'], 'data_nascita' => $nascita, 'comune_id' => $comune, 'consenso' => true]);
$st = db()->prepare('INSERT INTO preventivi (cliente_id, creato_il, origine, comune_id, comune_nome, data_nascita, eta, modello_id, veicolo, garanzie, dettaglio, totale, nome, email, telefono, note, consenso_privacy, ip_hash)
    VALUES (?, UTC_TIMESTAMP(), \'sito\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
$st->execute([$clienteId, $comune, $r['comune'], $nascita, $r['eta'], $modello, $r['veicolo'], $nomiGaranzie, json_encode($r, JSON_UNESCAPED_UNICODE), $r['totale'],
    $c['nome'], $c['email'], $c['telefono'] ?: null, $c['note'] ?: null, client_ip_hash()]);
$id = (int)db()->lastInsertId();
db()->commit();

// avviso all'agenzia (se è impostato l'indirizzo)
$to = trim((string)impostazione('email_notifiche', ''));
if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
    $righe = implode("\n", array_map(fn($x) => $x['nome'] . ': ' . ($x['importo'] === null ? 'su richiesta' : number_format((float)$x['importo'], 2, ',', '.') . ' €'), $r['righe']));
    $m = EmailPreventivo::avviso("Nuova richiesta di preventivo n. $id", [
        'Cliente' => $c['nome'], 'Email' => $c['email'], 'Telefono' => $c['telefono'] ?: '–', 'Comune' => $r['comune'],
        'Età' => $r['eta'] . ' anni', 'Veicolo' => $r['veicolo'], 'Garanzie' => $righe,
        'Stima' => number_format((float)$r['totale'], 2, ',', '.') . ' €', 'Note' => $c['note'] ?: '–',
    ], EmailPreventivo::sitoUrl() . "/admin/preventivi.php?id=$id");
    $err = Mailer::invia($to, $m['oggetto'], $m['html'], $m['testo'], $c['email'], 'Sito ACAT Servizi');
    Mailer::registra($clienteId, $id, 'avviso_agenzia', $to, $m['oggetto'], $err, null);
}
json_out(['ok' => true, 'id' => $id, 'stima' => stima_pubblica($r)]);
