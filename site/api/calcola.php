<?php
/* Calcola la stima. Con "mostra_prezzo = dopo_contatti" il risultato arriva solo da richiesta.php. */
declare(strict_types=1);
require __DIR__ . '/_init.php';

controlla_attivo();
if (!Limiti::consenti('calcola', 30)) json_out(['ok' => false, 'errore' => 'Troppe richieste, riprova tra un minuto.'], 429);
if (impostazione('mostra_prezzo', 'subito') !== 'subito') json_out(['ok' => false, 'errore' => 'Lascia i tuoi contatti per vedere la stima.'], 403);

$d = input_json();
if (e_capitale($d)) {
    [$listini, $capitale, $risposte, $fraz] = dati_capitale($d);
    [$r, $err] = Preventivatore::calcolaCapitale($listini, $capitale, $risposte, $fraz);
    if ($err) json_out(['ok' => false, 'errore' => $err], 422);
    json_out(['ok' => true, 'stima' => stima_pubblica($r)]);
}
[$comune, $nascita, $modello, $garanzie, $pag] = dati_calcolo($d);
[$r, $err] = Preventivatore::calcola($comune, $nascita, $modello, $garanzie, false, $pag);
if ($err) json_out(['ok' => false, 'errore' => $err], 422);
json_out(['ok' => true, 'stima' => stima_pubblica($r)]);
