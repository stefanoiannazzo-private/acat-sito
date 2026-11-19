<?php
/* Pagina personale del preventivo inviato per email: si apre con il link segreto e permette di accettarlo. */
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

$t = (string)($_POST['t'] ?? $_GET['t'] ?? '');
$p = null;
if (preg_match('/^[a-f0-9]{64}$/', $t)) {
    $st = db()->prepare('SELECT p.*, c.nome AS cliente_nome FROM preventivi p LEFT JOIN clienti c ON c.id = p.cliente_id WHERE p.token_hash = ?');
    $st->execute([hash('sha256', $t)]);
    $p = $st->fetch() ?: null;
}
$scaduto = $p && $p['valido_fino'] && $p['valido_fino'] < date('Y-m-d');
$esito = null;

if ($p && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['azione'] ?? '') === 'accetta') {
    if (!Limiti::consenti('accetta', 10)) { $esito = 'Troppi tentativi: riprova tra un minuto.'; }
    elseif ($scaduto) { $esito = 'Il preventivo è scaduto: chiamaci e lo aggiorniamo.'; }
    elseif (!$p['accettato_il']) {
        db()->prepare("UPDATE preventivi SET stato = 'accettato', accettato_il = UTC_TIMESTAMP() WHERE id = ?")->execute([$p['id']]);
        Clienti::impostaStato((int)$p['cliente_id'], 'preventivo_accettato');
        $to = trim((string)impostazione('email_notifiche', ''));
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $m = EmailPreventivo::avviso('Preventivo n. ' . $p['id'] . ' accettato da ' . $p['cliente_nome'], [
                'Cliente' => (string)$p['cliente_nome'], 'Email' => (string)$p['email'], 'Telefono' => $p['telefono'] ?: '–',
                (empty($p['capitale']) ? 'Veicolo' : 'Polizza') => (string)$p['veicolo'], 'Totale' => number_format((float)EmailPreventivo::totale($p), 2, ',', '.') . ' €',
            ], EmailPreventivo::sitoUrl() . '/admin/preventivi.php?id=' . $p['id']);
            Mailer::registra((int)$p['cliente_id'], (int)$p['id'], 'avviso_accettazione', $to, $m['oggetto'], Mailer::invia($to, $m['oggetto'], $m['html'], $m['testo'], $p['email'], 'Sito ACAT Servizi'), null);
        }
        $p['accettato_il'] = gmdate('Y-m-d H:i:s');
    }
}
$euro = fn($v) => $v === null ? 'su richiesta' : '€ ' . number_format((float)$v, 2, ',', '.');
?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Il tuo preventivo · ACAT Servizi</title>
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="vendor/fonts/fonts.css">
<style>
  :root { --ink:#0e2340; --muted:#5a6880; --accent:#b3302a; --line:#e3e8ef; }
  * { box-sizing: border-box; }
  body { margin:0; background:#eef2f7; color:var(--ink); font:16px/1.55 "Instrument Sans", system-ui, sans-serif; }
  .w { max-width: 640px; margin: 0 auto; padding: 24px 16px 48px; }
  .card { background:#fff; border-radius:16px; overflow:hidden; }
  .top { padding:24px 28px; border-bottom:1px solid var(--line); }
  .in { padding:28px; }
  .k { margin:0 0 6px; font-size:13px; letter-spacing:.08em; text-transform:uppercase; color:var(--accent); font-weight:600; }
  h1 { margin:0 0 16px; font:400 30px/1.2 "Instrument Serif", Georgia, serif; }
  .meta { margin:0 0 20px; display:grid; grid-template-columns:auto 1fr; gap:4px 16px; font-size:15px; }
  .meta dt { color:var(--muted); } .meta dd { margin:0; }
  table { width:100%; border-collapse:collapse; }
  td { padding:12px 0; border-bottom:1px solid var(--line); }
  td:last-child { text-align:right; font-weight:600; white-space:nowrap; }
  .tot td { border:0; padding-top:18px; } .tot td:last-child { font:400 32px/1 "Instrument Serif", Georgia, serif; }
  .note { color:var(--muted); font-size:13px; margin:6px 0 0; }
  .btns { display:flex; flex-wrap:wrap; gap:12px; margin-top:24px; }
  .btn { display:inline-flex; align-items:center; height:48px; padding:0 20px; border-radius:10px; border:1px solid var(--accent); background:var(--accent); color:#fff; font:600 16px/1 "Instrument Sans", system-ui, sans-serif; text-decoration:none; cursor:pointer; }
  .btn--ghost { background:#fff; color:var(--ink); border-color:#c7d0dd; }
  .ok { padding:14px 16px; border-radius:10px; background:#e3f3ea; color:#14532d; margin-top:20px; }
  .ko { padding:14px 16px; border-radius:10px; background:#fbe8e6; color:#7f1d1d; margin-top:20px; }
  .foot { padding:20px 28px; background:var(--ink); color:#c9d3e2; font-size:13px; }
  .foot b { color:#fff; }
</style>
</head>
<body>
<div class="w"><div class="card">
  <div class="top"><a href="./"><img src="logo-acat.svg" alt="ACAT Servizi" width="150" height="32"></a></div>
  <div class="in">
<?php if (!$p): ?>
    <h1>Preventivo non trovato</h1>
    <p>Il link non è valido o è stato sostituito da un invio più recente. Chiamaci al <a href="tel:<?= EmailPreventivo::TEL_LINK ?>"><?= EmailPreventivo::TELEFONO ?></a> e ti aiutiamo subito.</p>
<?php else:
    $righe = EmailPreventivo::righe($p); ?>
    <p class="k">Preventivo n. <?= (int)$p['id'] ?></p>
    <h1>Il tuo preventivo<?= empty($p['capitale']) ? ' RC auto' : '' ?></h1>
    <dl class="meta">
      <?php foreach (['Intestatario' => $p['cliente_nome'], (empty($p['capitale']) ? 'Veicolo' : 'Polizza') => $p['veicolo'], 'Residenza' => $p['comune_nome'], 'Compagnia' => $p['compagnia'],
          'Decorrenza' => $p['decorrenza'] ? date('d/m/Y', strtotime($p['decorrenza'])) : null] as $k => $v) if ($v): ?>
        <dt><?= e($k) ?></dt><dd><?= e((string)$v) ?></dd>
      <?php endif; ?>
    </dl>
    <table>
      <?php foreach ($righe as $r): ?><tr><td><?= e($r['nome']) ?></td><td><?= $euro($r['importo']) ?></td></tr><?php endforeach; ?>
      <tr class="tot"><td><b>Totale annuo</b></td><td><?= $euro(EmailPreventivo::totale($p)) ?></td></tr>
    </table>
    <?php if ($p['valido_fino']): ?><p class="note">Offerta valida fino al <?= e(date('d/m/Y', strtotime($p['valido_fino']))) ?>.</p><?php endif; ?>
    <?php if ($esito): ?><p class="ko" role="alert"><?= e($esito) ?></p><?php endif; ?>
    <?php if ($p['accettato_il']): ?>
      <p class="ok" role="status"><b>Preventivo accettato.</b> Grazie! Ti richiamiamo a breve per firmare e completare la polizza.</p>
    <?php elseif ($scaduto): ?>
      <p class="ko">Questo preventivo è scaduto. Chiamaci e lo aggiorniamo.</p>
    <?php else: ?>
      <form method="post" class="btns">
        <input type="hidden" name="t" value="<?= e($t) ?>"><input type="hidden" name="azione" value="accetta">
        <button class="btn">Accetto il preventivo</button>
        <a class="btn btn--ghost" href="tel:<?= EmailPreventivo::TEL_LINK ?>">Chiama <?= EmailPreventivo::TELEFONO ?></a>
      </form>
      <p class="note">Accettando ci autorizzi a ricontattarti per la firma. Il contratto si conclude solo con la firma, dopo aver ricevuto la documentazione precontrattuale.</p>
    <?php endif; ?>
<?php endif; ?>
  </div>
  <div class="foot"><b>ACAT Servizi</b> · <?= EmailPreventivo::INDIRIZZO ?><br><?= EmailPreventivo::ORARI ?><br><?= EmailPreventivo::SOCIETA ?> · <a href="privacy.html" style="color:#c9d3e2">Privacy</a></div>
</div></div>
</body>
</html>
