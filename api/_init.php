<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

set_exception_handler(function (Throwable $e): void {
    error_log('[preventivatore] ' . $e->getMessage());
    json_out(['ok' => false, 'errore' => 'Servizio momentaneamente non disponibile. Riprova più tardi o chiamaci.'], 503);
});

/** Corpo JSON della richiesta, solo POST dallo stesso sito */
function input_json(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['ok' => false, 'errore' => 'Metodo non consentito.'], 405);
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $o = parse_url($origin);
        $oh = strtolower(($o['host'] ?? '') . (isset($o['port']) ? ':' . $o['port'] : ''));
        if ($oh !== strtolower((string)($_SERVER['HTTP_HOST'] ?? ''))) json_out(['ok' => false, 'errore' => 'Richiesta non consentita.'], 403);
    }
    $raw = file_get_contents('php://input', false, null, 0, 20000);
    $d = json_decode((string)$raw, true);
    if (!is_array($d)) json_out(['ok' => false, 'errore' => 'Dati non validi.'], 400);
    return $d;
}

function controlla_attivo(): void {
    if (impostazione('preventivatore_attivo', '1') !== '1') json_out(['ok' => false, 'errore' => 'Il preventivatore online è momentaneamente sospeso: chiamaci o scrivici per un preventivo.'], 503);
}

/** Dati del veicolo e del conducente dal corpo della richiesta */
function dati_calcolo(array $d): array {
    $garanzie = array_values(array_filter(array_map('intval', (array)($d['garanzie'] ?? [])), fn($x) => $x > 0));
    return [(int)($d['comune_id'] ?? 0), (string)($d['nascita'] ?? ''), (int)($d['modello_id'] ?? 0), array_slice($garanzie, 0, 30)];
}

/** Dati di contatto validati: [dati, errore] */
function dati_contatto(array $d): array {
    $nome = trim(mb_substr((string)($d['nome'] ?? ''), 0, 120));
    $email = trim(mb_substr((string)($d['email'] ?? ''), 0, 190));
    $tel = trim(mb_substr((string)($d['telefono'] ?? ''), 0, 40));
    $note = trim(mb_substr((string)($d['note'] ?? ''), 0, 2000));
    if ($nome === '') return [null, 'Scrivi nome e cognome.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [null, 'Controlla l\'indirizzo email.'];
    if ($tel !== '' && !preg_match('/^[0-9 +().\/-]{6,40}$/', $tel)) return [null, 'Controlla il numero di telefono.'];
    if (empty($d['consenso'])) return [null, 'Serve il consenso al trattamento dei dati per inviarti il preventivo.'];
    return [['nome' => $nome, 'email' => $email, 'telefono' => $tel, 'note' => $note], null];
}

/** Toglie dalla stima i dettagli interni della tariffa prima di mandarla al browser */
function stima_pubblica(array $r): array {
    unset($r['coefficiente']);
    $r['righe'] = array_map(function ($x) { unset($x['spiegazione']); return $x; }, $r['righe']);
    return $r;
}
