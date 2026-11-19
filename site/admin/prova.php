<?php
/* Calcola una stima con le tariffe attuali, mostrando come si arriva a ogni importo. */
declare(strict_types=1);
require __DIR__ . '/_init.php';
Auth::richiedi();

$ris = null; $err = null;
$in = calcolo_input();
if ($_SERVER['REQUEST_METHOD'] === 'POST') [$ris, $err] = calcola_da_input($in);
function risultato(?array $ris, ?string $err): void
{ ?>
  <?php if ($err): ?><p class="flash flash--errore"><?= e($err) ?></p>
  <?php elseif ($ris): ?>
    <p><b><?= e($ris['compagnia'] . ' – ' . $ris['prodotto']) ?></b> è il prezzo migliore<?= count($ris['confronto']['validi']) > 1 ? ' fra ' . count($ris['confronto']['validi']) . ' prodotti' : '' ?>.</p>
    <p><?= e($ris['comune']) ?> · <?= (int)$ris['eta'] ?> anni (<?= e($ris['fascia_eta'] ?? 'nessuna fascia') ?>) · <?= e($ris['veicolo']) ?><br><small class="muted">Coefficiente complessivo comune × marca × modello: <?= e(str_replace('.', ',', (string)$ris['coefficiente'])) ?></small></p>
    <table class="table"><tbody>
    <?php foreach ($ris['righe'] as $r): ?><tr><td><?= e($r['nome']) ?><br><small class="muted"><?= e($r['spiegazione']) ?></small></td><td class="right"><?= $r['importo'] === null ? 'su richiesta' : euro($r['importo']) ?></td></tr><?php endforeach; ?>
    <tr><td>Premio delle garanzie</td><td class="right"><?= euro($ris['premio_garanzie']) ?></td></tr>
    <?php foreach ($ris['sconti'] as $r): ?><tr><td><?= e($r['nome']) ?></td><td class="right"><?= euro($r['importo']) ?></td></tr><?php endforeach; ?>
    <?php if ($ris['limite']): ?><tr><td colspan="2" class="muted">Applicato il <?= e($ris['limite']) ?></td></tr><?php endif; ?>
    <tr class="tot"><td>Premio annuo<?= $ris['rate'] > 1 ? ' · ' . $ris['rate'] . ' rate da ' . euro($ris['rata']) : '' ?><?= $ris['durata'] > 1 ? ' · durata ' . $ris['durata'] . ' anni (' . euro($ris['totale_durata']) . ')' : '' ?></td><td class="right"><?= euro($ris['totale']) ?></td></tr>
    </tbody></table>
    <?php if ($ris['correzioni']): ?><p class="muted">Variazioni di prezzo del prodotto: <?= e(implode(' · ', $ris['correzioni'])) ?></p><?php endif; ?>
    <?php tabella_confronto($ris); ?>
  <?php else: ?><p class="muted">Compila i passi a sinistra: il risultato compare qui e si aggiorna da solo a ogni modifica.</p><?php endif; ?>
<?php }

// ricalcolo dal vivo: il modulo manda i dati a ogni modifica e riceve solo il risultato
if (!empty($_POST['dal_vivo'])) { risultato($ris, $err); exit; }
$marche = db()->query("SELECT id, CONCAT(nome, IF(tipo_veicolo = 'moto', ' (moto)', '')) AS n FROM marche ORDER BY tipo_veicolo, nome")->fetchAll(PDO::FETCH_KEY_PAIR);
$modelli = [];
if ($in['marca_id']) { $st = db()->prepare('SELECT id, nome FROM modelli WHERE marca_id = ? ORDER BY nome'); $st->execute([$in['marca_id']]); $modelli = $st->fetchAll(PDO::FETCH_KEY_PAIR); }
pagina_inizio('Prova calcolo', 'prova');
?>
<h1>Prova calcolo</h1>
<p class="intro">Usa le tariffe salvate in questo momento, anche per comuni, marche e modelli non attivi. Nulla viene registrato.</p>
<div class="cols cols--prova">
<form method="post" class="form" id="prova">
  <?= Auth::campoCsrf() ?>
  <?php campi_calcolo($in); ?>
  <fieldset class="passo"><legend><span>5</span> Contraente e veicolo</legend>
  <label>Comune di residenza
    <input name="comune" list="comuni-list" value="<?= e($in['comune']) ?>" autocomplete="off" data-cerca-comuni required>
    <input type="hidden" name="comune_id" value="<?= $in['comune_id'] ?: '' ?>">
    <datalist id="comuni-list"></datalist><small class="hint">Scrivi almeno 2 lettere e scegli dall'elenco</small>
  </label>
  <label>Data di nascita<input type="date" name="nascita" value="<?= e($in['nascita']) ?>" required></label>
  <label>Marca<select name="marca_id" data-marca required><option value="">Scegli…</option><?php foreach ($marche as $k => $v): ?><option value="<?= (int)$k ?>"<?= $in['marca_id'] === (int)$k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
  <label>Modello<select name="modello_id" data-modello required><option value="">Scegli…</option><?php foreach ($modelli as $k => $v): ?><option value="<?= (int)$k ?>"<?= $in['modello_id'] === (int)$k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
  </fieldset>
  <button class="btn">Calcola</button>
</form>
<section class="box prova-fisso">
  <h2>Risultato <small class="muted" id="prova-stato" aria-live="polite"></small></h2>
  <div id="prova-risultato"><?php risultato($ris, $err); ?></div>
</section>
</div>
<?php pagina_fine();
