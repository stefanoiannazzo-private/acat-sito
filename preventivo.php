<?php
/*
  ACAT Servizi - riceve il modulo "Richiedi un preventivo" (contatti.html) e lo inoltra per email.
  Da impostare prima della messa online: DESTINATARIO (la casella che riceve le richieste).
  Il mittente usa il dominio del sito, come richiede l'hosting per la funzione mail().
*/
const DESTINATARIO = ''; // es. 'info@acatservizi.it' - finché è vuoto il modulo risponde con un errore

function rispondi(int $codice, string $msg): void {
    http_response_code($codice);
    $vuoleJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    if ($vuoleJson) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => $codice < 300, 'message' => $msg]); }
    else { header('Location: contatti.html?' . ($codice < 300 ? 'inviato=1' : 'errore=1') . '#booking'); }
    exit;
}
function pulisci(?string $v, int $max = 500): string {
    $v = trim(str_replace(["\r", "\0"], '', (string) $v));
    return mb_substr(strip_tags($v), 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') rispondi(405, 'Metodo non consentito.');
if (DESTINATARIO === '') rispondi(503, 'Modulo non ancora configurato.');
// campo trappola: le persone non lo vedono, i bot sì
if (!empty($_POST['sito_web'])) rispondi(200, 'Inviato.');

$nome      = pulisci($_POST['nome'] ?? '', 120);
$email     = pulisci($_POST['email'] ?? '', 160);
$richiesta = pulisci($_POST['richiesta'] ?? '', 300);
$comune    = pulisci($_POST['comune'] ?? '', 120);

if ($nome === '' || $richiesta === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
    rispondi(422, 'Controlla nome, email e richiesta.');
}

// limite semplice: una richiesta ogni 30 secondi dallo stesso indirizzo
$lock = sys_get_temp_dir() . '/acat_prev_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
if (is_file($lock) && time() - filemtime($lock) < 30) rispondi(429, 'Attendi qualche secondo e riprova.');
@touch($lock);

$host = preg_replace('/^www\./', '', strtolower($_SERVER['HTTP_HOST'] ?? 'localhost'));
$host = preg_replace('/[^a-z0-9.-]/', '', $host);
$oggetto = '=?UTF-8?B?' . base64_encode("Richiesta di preventivo dal sito: $nome") . '?=';
$testo = "Nuova richiesta dal modulo del sito\n\n"
       . "Nome e cognome: $nome\n"
       . "Email: $email\n"
       . "Polizza o pratica: $richiesta\n"
       . "Comune: " . ($comune !== '' ? $comune : '-') . "\n\n"
       . "Inviata il " . date('d/m/Y H:i') . "\n";
$intestazioni = [
    'From: Sito ACAT Servizi <noreply@' . $host . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];
$ok = mail(DESTINATARIO, $oggetto, $testo, implode("\r\n", $intestazioni), '-f noreply@' . $host);
rispondi($ok ? 200 : 500, $ok ? 'Inviato.' : 'Invio non riuscito.');
