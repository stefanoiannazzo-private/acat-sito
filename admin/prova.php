<?php
/* Calcola una stima con le tariffe attuali, mostrando come si arriva a ogni importo. */
declare(strict_types=1);
require __DIR__ . '/_init.php';
Auth::richiedi();

$garanzie = Preventivatore::garanzie();
$ris = null; $err = null;
$in = ['comune_id' => (int)($_POST['comune_id'] ?? 0), 'comune' => (string)($_POST['comune'] ?? ''), 'nascita' => (string)($_POST['nascita'] ?? ''),
       'marca_id' => (int)($_POST['marca_id'] ?? 0), 'modello_id' => (int)($_POST['modello_id'] ?? 0), 'garanzie' => array_map('intval', (array)($_POST['garanzie'] ?? []))];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$ris, $err] = Preventivatore::calcola($in['comune_id'], $in['nascita'], $in['modello_id'], $in['garanzie'], true);
}
$marche = db()->query("SELECT id, CONCAT(nome, IF(tipo_veicolo = 'moto', ' (moto)', '')) AS n FROM marche ORDER BY tipo_veicolo, nome")->fetchAll(PDO::FETCH_KEY_PAIR);
$modelli = [];
if ($in['marca_id']) { $st = db()->prepare('SELECT id, nome FROM modelli WHERE marca_id = ? ORDER BY nome'); $st->execute([$in['marca_id']]); $modelli = $st->fetchAll(PDO::FETCH_KEY_PAIR); }
pagina_inizio('Prova calcolo', 'prova');
?>
<h1>Prova calcolo</h1>
<p class="intro">Usa le tariffe salvate in questo momento, anche per comuni, marche e modelli non attivi. Nulla viene registrato.</p>
<div class="cols">
<form method="post" class="form" id="prova">
  <?= Auth::campoCsrf() ?>
  <label>Comune di residenza
    <input name="comune" list="comuni-list" value="<?= e($in['comune']) ?>" autocomplete="off" data-cerca-comuni required placeholder="Scrivi almeno 2 lettere">
    <input type="hidden" name="comune_id" value="<?= $in['comune_id'] ?: '' ?>">
    <datalist id="comuni-list"></datalist>
  </label>
  <label>Data di nascita<input type="date" name="nascita" value="<?= e($in['nascita']) ?>" required></label>
  <label>Marca<select name="marca_id" data-marca required><option value="">Scegli…</option><?php foreach ($marche as $k => $v): ?><option value="<?= (int)$k ?>"<?= $in['marca_id'] === (int)$k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
  <label>Modello<select name="modello_id" data-modello required><option value="">Scegli…</option><?php foreach ($modelli as $k => $v): ?><option value="<?= (int)$k ?>"<?= $in['modello_id'] === (int)$k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
  <fieldset><legend>Garanzie</legend>
  <?php foreach ($garanzie as $g): ?>
    <label class="check"><input type="checkbox" name="garanzie[]" value="<?= (int)$g['id'] ?>"<?= $g['principale'] ? ' checked disabled' : (in_array((int)$g['id'], $in['garanzie'], true) ? ' checked' : '') ?>> <?= e($g['nome']) ?></label>
  <?php endforeach; ?>
  </fieldset>
  <button class="btn">Calcola</button>
</form>
<section class="box">
  <h2>Risultato</h2>
  <?php if ($err): ?><p class="flash flash--errore"><?= e($err) ?></p>
  <?php elseif ($ris): ?>
    <p><?= e($ris['comune']) ?> · <?= (int)$ris['eta'] ?> anni (<?= e($ris['fascia_eta'] ?? 'nessuna fascia') ?>) · <?= e($ris['veicolo']) ?><br><small class="muted">Coefficiente complessivo comune × marca × modello: <?= e(str_replace('.', ',', (string)$ris['coefficiente'])) ?></small></p>
    <table class="table"><tbody>
    <?php foreach ($ris['righe'] as $r): ?><tr><td><?= e($r['nome']) ?><br><small class="muted"><?= e($r['spiegazione']) ?></small></td><td class="right"><?= $r['importo'] === null ? 'su richiesta' : euro($r['importo']) ?></td></tr><?php endforeach; ?>
    <tr class="tot"><td>Totale</td><td class="right"><?= euro($ris['totale']) ?></td></tr>
    </tbody></table>
  <?php else: ?><p class="muted">Compila i dati e premi Calcola.</p><?php endif; ?>
</section>
</div>
<?php pagina_fine();
