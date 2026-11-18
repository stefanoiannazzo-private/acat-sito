<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
Auth::richiedi();

$campi = [
    'preventivatore_attivo' => ['Preventivatore online', 'scelta', ['1' => 'Attivo', '0' => 'Sospeso (mostra un messaggio e invita a chiamare)']],
    'mostra_prezzo' => ['Quando mostrare la stima', 'scelta', ['subito' => 'Subito, dopo il calcolo', 'dopo_contatti' => 'Solo dopo che il visitatore lascia nome ed email']],
    'email_notifiche' => ['Email che riceve le richieste', 'email', null],
    'premio_minimo_rc' => ['Premio minimo RC (€)', 'numero', null],
    'arrotondamento' => ['Arrotondamento degli importi', 'scelta', ['centesimi' => 'Ai centesimi', 'euro' => 'All\'euro']],
    'eta_minima' => ['Età minima del contraente', 'numero', null],
    'avviso' => ['Avviso sotto il risultato', 'testo', null],
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $err = [];
    foreach ($campi as $k => [$lab, $tipo, $opz]) {
        $v = trim((string)($_POST[$k] ?? ''));
        if ($tipo === 'scelta' && !isset($opz[$v])) $err[] = "$lab: valore non valido.";
        if ($tipo === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $err[] = "$lab: indirizzo non valido.";
        if ($tipo === 'numero') { $n = num($v); if ($n === null || $n < 0) $err[] = "$lab: serve un numero."; else $v = (string)$n; }
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
<h1>Impostazioni del preventivatore</h1>
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
      <input name="<?= $k ?>" value="<?= e(str_replace('.', ',', (string)$v)) ?>"<?= $tipo === 'email' ? ' type="email"' : ' inputmode="decimal"' ?>>
    <?php endif; ?>
    </label>
  <?php endforeach; ?>
  <button class="btn">Salva</button>
</form>
<?php pagina_fine();
