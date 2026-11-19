<?php
/* Documenti dei clienti: caricamento (con lettura del contratto), scaricamento, eliminazione. */
declare(strict_types=1);
require __DIR__ . '/_init.php';
$me = Auth::richiedi();

// scaricamento
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $d = Documenti::trova((int)$_GET['id']);
    if (!$d || !is_file(Documenti::percorso($d))) { http_response_code(404); exit('Documento non trovato.'); }
    $inline = isset($_GET['vedi']) && in_array($d['mime'], ['application/pdf', 'image/jpeg', 'image/png'], true);
    header_remove('Content-Security-Policy');
    header('Content-Type: ' . $d['mime']);
    header('Content-Length: ' . filesize(Documenti::percorso($d)));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._ -]/', '_', $d['nome']) . "\"; filename*=UTF-8''" . rawurlencode($d['nome']));
    readfile(Documenti::percorso($d));
    exit;
}

$cid = (int)($_POST['cliente_id'] ?? 0);
$torna = 'clienti.php?id=' . $cid . '#documenti';
if (($_POST['azione'] ?? '') === 'carica') {
    $pid = (int)($_POST['polizza_id'] ?? 0) ?: null;
    [$id, $err] = Documenti::salva($_FILES['file'] ?? [], $cid, $pid, trim((string)($_POST['descrizione'] ?? '')) ?: null, $me['email']);
    if ($err) { flash('errore', $err); vai($pid ? "polizze.php?id=$pid" : $torna); }
    Auth::log('carica', 'documenti', $id, (string)($_FILES['file']['name'] ?? ''));
    if (!empty($_POST['leggi'])) {
        $d = Documenti::trova($id);
        $comp = db()->query('SELECT id, nome FROM compagnie WHERE attivo = 1')->fetchAll(PDO::FETCH_KEY_PAIR);
        $_SESSION['lettura'][$id] = LettoreContratti::analizza(LettoreContratti::testo(Documenti::percorso($d), $d['mime']), $comp);
        vai($pid ? "polizze.php?modifica=$pid&doc=$id" : "polizze.php?nuovo=1&cliente=$cid&doc=$id");
    }
    flash('ok', 'Documento caricato.');
    vai($pid ? "polizze.php?id=$pid" : $torna);
}
if (($_POST['azione'] ?? '') === 'elimina') {
    $d = Documenti::trova((int)($_POST['id'] ?? 0));
    if ($d) { Documenti::elimina((int)$d['id']); Auth::log('elimina', 'documenti', (int)$d['id'], $d['nome']); flash('ok', 'Documento eliminato.'); }
    vai(!empty($_POST['polizza']) ? 'polizze.php?id=' . (int)$_POST['polizza'] : 'clienti.php?id=' . (int)($d['cliente_id'] ?? 0) . '#documenti');
}
vai('clienti.php');
