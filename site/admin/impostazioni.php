<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
Auth::richiedi();

$campi = [
    'preventivatore_attivo' => ['Preventivatore online', 'scelta', ['1' => 'Attivo', '0' => 'Sospeso (mostra un messaggio e invita a chiamare)']],
    'mostra_prezzo' => ['Quando mostrare la stima', 'scelta', ['subito' => 'Subito, dopo il calcolo', 'dopo_contatti' => 'Solo dopo che il visitatore lascia nome ed email']],
    'email_notifiche' => ['Email dell\'agenzia che riceve gli avvisi (nuove richieste, preventivi accettati)', 'email', null],
    'email_mittente_nome' => ['Nome del mittente nelle email ai clienti', 'riga', null],
    'email_risposta' => ['Indirizzo a cui rispondono i clienti (vuoto = la casella che invia)', 'email', null],
    'validita_giorni' => ['Validità proposta del preventivo (giorni)', 'numero', null],
    'messaggio_preventivo' => ['Messaggio standard nella email del preventivo', 'testo', null],
    'premio_minimo_rc' => ['Premio minimo RC (€)', 'numero', null],
    'arrotondamento' => ['Arrotondamento degli importi', 'scelta', ['centesimi' => 'Ai centesimi', 'euro' => 'All\'euro']],
    'eta_minima' => ['Età minima del contraente', 'numero', null],
    'avviso' => ['Avviso sotto il risultato', 'testo', null],
    // avvisi di scadenza delle polizze
    'avvisi_attivi' => ['Avvisi di scadenza', 'scelta', ['1' => 'Attivi', '0' => 'Spenti']],
    'avvisi_destinatari' => ['A chi inviare gli avvisi', 'scelta', ['agenzia' => 'Solo all\'agenzia', 'agenzia_cliente' => 'All\'agenzia e al cliente (solo per le polizze con "comunica al cliente")']],
    'avvisi_email_agenzia' => ['Email dell\'agenzia per le scadenze (vuoto = quella degli avvisi generali)', 'email', null],
    'avvisi_giorni_agenzia' => ['Giorni di anticipo per l\'avviso all\'agenzia', 'intero', [0, 120]],
    'avvisi_giorni_cliente' => ['Giorni di anticipo per l\'avviso al cliente', 'intero', [0, 120]],
    'avvisi_ora' => ['Ora di invio (0-23)', 'intero', [0, 23]],
    'avvisi_rate' => ['Avvisa anche per le rate (semestrali, trimestrali, mensili)', 'scelta', ['1' => 'Sì', '0' => 'No, solo per la scadenza dell\'annualità']],
    'avvisi_oggetto_agenzia' => ['Oggetto dell\'avviso all\'agenzia', 'riga', null],
    'avvisi_testo_agenzia' => ['Testo dell\'avviso all\'agenzia', 'testo', null],
    'avvisi_oggetto_cliente' => ['Oggetto dell\'avviso al cliente', 'riga', null],
    'avvisi_testo_cliente' => ['Testo dell\'avviso al cliente', 'testo', null],
    'documenti_max_mb' => ['Dimensione massima dei documenti (MB)', 'intero', [1, 100]],
];
$sezioni = ['preventivatore_attivo' => 'Preventivatore ed email', 'avvisi_attivi' => 'Avvisi di scadenza delle polizze', 'documenti_max_mb' => 'Documenti dei clienti'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'prova_email') {
    $a = trim((string)($_POST['a'] ?? ''));
    $m = EmailPreventivo::avviso('Email di prova dal pannello ACAT', ['Inviata da' => (string)Auth::utente()['email'], 'Metodo' => Mailer::configurato() ? 'SMTP (casella del dominio)' : 'funzione mail() dell\'hosting'], EmailPreventivo::sitoUrl() . '/admin/');
    $errore = Mailer::invia($a, $m['oggetto'], $m['html'], $m['testo']);
    Mailer::registra(null, null, 'prova', $a ?: '-', $m['oggetto'], $errore, Auth::utente()['email']);
    $errore ? flash('errore', 'Invio non riuscito: ' . $errore) : flash('ok', "Email di prova inviata a $a: controlla la casella (anche lo spam).");
    vai('impostazioni.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'controlla_avvisi') {
    db()->prepare("UPDATE impostazioni SET valore = '' WHERE chiave = 'avvisi_ultimo_controllo'")->execute();
    $r = Avvisi::esegui();
    flash($r['errori'] ? 'errore' : 'ok', "Controllo eseguito: {$r['inviati']} avvisi inviati, {$r['errori']} errori, {$r['saltati']} saltati (clienti senza email).");
    vai('impostazioni.php#avvisi');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $err = [];
    foreach ($campi as $k => [$lab, $tipo, $opz]) {
        $v = trim((string)($_POST[$k] ?? ''));
        if ($tipo === 'scelta' && !isset($opz[$v])) $err[] = "$lab: valore non valido.";
        if ($tipo === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $err[] = "$lab: indirizzo non valido.";
        if ($tipo === 'numero') { $n = num($v); if ($n === null || $n < 0) $err[] = "$lab: serve un numero."; else $v = (string)$n; }
        if ($tipo === 'riga' && ($v === '' || mb_strlen($v) > 150)) $err[] = "$lab: da 1 a 150 caratteri.";
        if ($tipo === 'intero' && (!preg_match('/^\d+$/', $v) || (int)$v < $opz[0] || (int)$v > $opz[1])) $err[] = "$lab: un numero da {$opz[0]} a {$opz[1]}.";
        if ($tipo === 'testo' && mb_strlen($v) > 2000) $err[] = "$lab: al massimo 1000 caratteri.";
        $_POST[$k] = $v;
    }
    if (!$err) {
        $st = db()->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?, ?) ON DUPLICATE KEY UPDATE valore = VALUES(valore)');
        foreach ($campi as $k => $_) $st->execute([$k, $_POST[$k]]);
        Auth::log('modifica', 'impostazioni', null, array_intersect_key($_POST, $campi));
        flash('ok', 'Impostazioni salvate.');
        vai('impostazioni.php');
    }
    foreach ($err as $m) flash('errore', $m);
}
pagina_inizio('Impostazioni', 'impostazioni');
?>
<h1>Impostazioni</h1>
<h2>Invio delle email</h2>
<?php if (Mailer::configurato()): ?>
  <div class="smtp smtp--ok"><b>Casella collegata.</b><span>Le email partono da <?= e(Mailer::mittente()) ?> tramite il server di posta di Aruba.</span></div>
<?php else: ?>
  <div class="smtp smtp--no"><b>Casella di posta non ancora collegata.</b><span>Le email partono con la funzione di base dell'hosting: possono arrivare in ritardo o finire nello spam. Per collegare una casella del dominio servono i secrets SMTP_USER e SMTP_PASS su GitHub (vedi le istruzioni nel README).</span></div>
<?php endif; ?>
<form method="post" class="form form--inline">
  <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="prova_email">
  <label>Invia un'email di prova a<input type="email" name="a" required value="<?= e((string)Auth::utente()['email']) ?>"></label>
  <button class="btn btn--ghost">Invia prova</button>
</form>
<form method="post" class="form form--narrow">
  <?= Auth::campoCsrf() ?>
  <?php foreach ($campi as $k => [$lab, $tipo, $opz]):
      $v = $_POST[$k] ?? impostazione($k, '');
      if (isset($sezioni[$k])): ?>
    <h2 id="<?= $k === 'avvisi_attivi' ? 'avvisi' : ($k === 'documenti_max_mb' ? 'documenti' : 'preventivatore') ?>"><?= e($sezioni[$k]) ?></h2>
    <?php if ($k === 'avvisi_attivi'): ?>
      <p class="intro">Esempio: polizza annuale che scade il 2 gennaio, anticipo 15 giorni, ora 9 → il 18 dicembre dalle 9:00 parte l'email all'agenzia (e, se scelto, al cliente). Nei testi puoi usare: {cliente}, {compagnia}, {prodotto}, {numero}, {scadenza}, {tipo_scadenza}, {frazionamento}, {premio}, {oggetto}, {email_cliente}, {telefono_cliente}. L'email all'agenzia contiene sempre il pulsante per aprire la scheda del cliente.</p>
    <?php elseif ($k === 'documenti_max_mb'): ?>
      <p class="intro">Formati ammessi: PDF, Word (doc, docx), JPG, PNG. Il limite non può superare quello del server: <?= e(Documenti::mb(Documenti::limiteServer())) ?>.</p>
    <?php endif; endif; ?>
    <label><?= e($lab) ?>
    <?php if ($tipo === 'scelta'): ?>
      <select name="<?= $k ?>"><?php foreach ($opz as $ov => $ol): ?><option value="<?= e((string)$ov) ?>"<?= (string)$v === (string)$ov ? ' selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
    <?php elseif ($tipo === 'testo'): ?>
      <textarea name="<?= $k ?>" rows="4"><?= e((string)$v) ?></textarea>
    <?php else: ?>
      <input name="<?= $k ?>" value="<?= e($tipo === 'numero' ? str_replace('.', ',', (string)$v) : (string)$v) ?>"<?= $tipo === 'email' ? ' type="email"' : ($tipo === 'numero' || $tipo === 'intero' ? ' inputmode="numeric"' : '') ?>>
    <?php endif; ?>
    </label>
  <?php endforeach; ?>
  <button class="btn">Salva</button>
</form>
<h2>Invio automatico degli avvisi</h2>
<?php $ult = (string)impostazione('avvisi_ultimo_controllo', ''); ?>
<p>Ultimo controllo delle scadenze: <b><?= $ult ? e(data_it($ult)) : 'mai' ?></b>. Il controllo gira ogni ora con il cron dell'hosting; se il cron non è attivo, parte comunque quando qualcuno apre il pannello.</p>
<p>Indirizzo da chiamare ogni ora (pannello Aruba → Hosting Linux → Processi Cron):</p>
<pre class="code"><?= e(EmailPreventivo::sitoUrl() . '/api/cron.php?k=' . chiave_cron()) ?></pre>
<form method="post" class="form form--inline"><?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="controlla_avvisi"><button class="btn btn--ghost">Controlla le scadenze adesso</button></form>
<h2>Controllo del server</h2>
<?php
  $arch = null; try { $arch = is_writable(Documenti::cartella()); } catch (Throwable) { $arch = false; }
  $ck = [
      'Cartella dei documenti scrivibile' => $arch,
      'Lettura dei PDF (libreria installata)' => class_exists(\Smalot\PdfParser\Parser::class),
      'Lettura dei file Word (estensione zip)' => class_exists(ZipArchive::class),
      'Riconoscimento del tipo di file (fileinfo)' => class_exists(finfo::class),
      'Invio email tramite casella collegata' => Mailer::configurato(),
  ]; ?>
<ul class="log"><?php foreach ($ck as $l => $ok): ?><li><?= $ok ? '<span class="tag tag--ok">ok</span>' : '<span class="tag tag--nuovo">no</span>' ?> <?= e($l) ?></li><?php endforeach; ?>
  <li class="muted">PHP <?= e(PHP_VERSION) ?> · limite di caricamento del server <?= e(Documenti::mb(Documenti::limiteServer())) ?></li></ul>
<?php pagina_fine();
