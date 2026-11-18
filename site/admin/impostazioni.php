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
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'prova_email') {
    $a = trim((string)($_POST['a'] ?? ''));
    $m = EmailPreventivo::avviso('Email di prova dal pannello ACAT', ['Inviata da' => (string)Auth::utente()['email'], 'Metodo' => Mailer::configurato() ? 'SMTP (casella del dominio)' : 'funzione mail() dell\'hosting'], EmailPreventivo::sitoUrl() . '/admin/');
    $errore = Mailer::invia($a, $m['oggetto'], $m['html'], $m['testo']);
    Mailer::registra(null, null, 'prova', $a ?: '-', $m['oggetto'], $errore, Auth::utente()['email']);
    $errore ? flash('errore', 'Invio non riuscito: ' . $errore) : flash('ok', "Email di prova inviata a $a: controlla la casella (anche lo spam).");
    vai('impostazioni.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $err = [];
    foreach ($campi as $k => [$lab, $tipo, $opz]) {
        $v = trim((string)($_POST[$k] ?? ''));
        if ($tipo === 'scelta' && !isset($opz[$v])) $err[] = "$lab: valore non valido.";
        if ($tipo === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $err[] = "$lab: indirizzo non valido.";
        if ($tipo === 'numero') { $n = num($v); if ($n === null || $n < 0) $err[] = "$lab: serve un numero."; else $v = (string)$n; }
        if ($tipo === 'riga' && ($v === '' || mb_strlen($v) > 80)) $err[] = "$lab: da 1 a 80 caratteri.";
        if ($tipo === 'testo' && mb_strlen($v) > 1000) $err[] = "$lab: al massimo 1000 caratteri.";
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
<h2>Preventivatore ed email</h2>
<form method="post" class="form form--narrow">
  <?= Auth::campoCsrf() ?>
  <?php foreach ($campi as $k => [$lab, $tipo, $opz]):
      $v = $_POST[$k] ?? impostazione($k, ''); ?>
    <label><?= e($lab) ?>
    <?php if ($tipo === 'scelta'): ?>
      <select name="<?= $k ?>"><?php foreach ($opz as $ov => $ol): ?><option value="<?= e((string)$ov) ?>"<?= (string)$v === (string)$ov ? ' selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
    <?php elseif ($tipo === 'testo'): ?>
      <textarea name="<?= $k ?>" rows="4"><?= e((string)$v) ?></textarea>
    <?php else: ?>
      <input name="<?= $k ?>" value="<?= e($tipo === 'numero' ? str_replace('.', ',', (string)$v) : (string)$v) ?>"<?= $tipo === 'email' ? ' type="email"' : ($tipo === 'numero' ? ' inputmode="decimal"' : '') ?>>
    <?php endif; ?>
    </label>
  <?php endforeach; ?>
  <button class="btn">Salva</button>
</form>
<?php pagina_fine();
