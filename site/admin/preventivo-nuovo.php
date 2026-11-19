<?php
/* Preventivo fatto in agenzia per un cliente dell'anagrafica: stesso calcolo del sito, poi si completa e si invia. */
declare(strict_types=1);
require __DIR__ . '/_init.php';
$me = Auth::richiedi();

$cid = (int)($_GET['cliente'] ?? $_POST['cliente'] ?? 0);
$st = db()->prepare('SELECT c.*, CONCAT(co.nome, \' (\', co.sigla, \')\') AS comune FROM clienti c LEFT JOIN comuni co ON co.id = c.comune_id WHERE c.id = ?');
$st->execute([$cid]);
$c = $st->fetch() ?: vai('clienti.php');

$in = calcolo_input(['comune_id' => $c['comune_id'] ?? 0, 'comune' => $c['comune'] ?? '', 'nascita' => $c['data_nascita'] ?? '']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$r, $err] = calcola_da_input($in);
    if ($err) flash('errore', $err);
    else {
        db()->prepare("INSERT INTO preventivi (cliente_id, creato_il, origine, creato_da, comune_id, comune_nome, data_nascita, eta, modello_id, prodotto_id, frazionamento, durata_anni, compagnia, veicolo, garanzie, dettaglio, totale, nome, email, telefono, consenso_privacy, stato)
            VALUES (?, UTC_TIMESTAMP(), 'backoffice', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_lavorazione')")
            ->execute([$cid, $me['email'], $in['comune_id'], $r['comune'], $in['nascita'], $r['eta'], $in['modello_id'], $r['prodotto_id'], $r['frazionamento'], $r['durata'], $r['compagnia'], $r['veicolo'],
                implode(', ', array_column($r['righe'], 'nome')), json_encode($r, JSON_UNESCAPED_UNICODE), $r['totale'], $c['nome'], $c['email'], $c['telefono'], (int)$c['consenso_privacy']]);
        $pid = (int)db()->lastInsertId();
        // completa l'anagrafica con comune e data di nascita, se mancavano
        db()->prepare('UPDATE clienti SET comune_id = COALESCE(comune_id, ?), data_nascita = COALESCE(data_nascita, ?), aggiornato_il = UTC_TIMESTAMP() WHERE id = ?')->execute([$in['comune_id'], $in['nascita'], $cid]);
        Auth::log('crea', 'preventivi', $pid, 'dal pannello per il cliente ' . $cid);
        flash('ok', 'Preventivo creato. Controlla gli importi, completa i dati e invialo al cliente.');
        vai('preventivi.php?id=' . $pid);
    }
}
$marche = db()->query("SELECT id, CONCAT(nome, IF(tipo_veicolo = 'moto', ' (moto)', '')) AS n FROM marche ORDER BY tipo_veicolo, nome")->fetchAll(PDO::FETCH_KEY_PAIR);
$modelli = [];
if ($in['marca_id']) { $st = db()->prepare('SELECT id, nome FROM modelli WHERE marca_id = ? ORDER BY nome'); $st->execute([$in['marca_id']]); $modelli = $st->fetchAll(PDO::FETCH_KEY_PAIR); }
pagina_inizio('Nuovo preventivo', 'clienti');
?>
<p class="crumbs"><a href="clienti.php?id=<?= $cid ?>">← <?= e($c['nome']) ?></a></p>
<h1>Nuovo preventivo per <?= e($c['nome']) ?></h1>
<p class="intro">Il calcolo usa le stesse tariffe del sito (anche per elementi non attivi). Nella pagina successiva puoi correggere gli importi, indicare la compagnia e inviarlo al cliente.</p>
<form method="post" class="form form--narrow">
  <?= Auth::campoCsrf() ?><input type="hidden" name="cliente" value="<?= $cid ?>">
  <label>Comune di residenza
    <input name="comune" list="comuni-list" value="<?= e($in['comune']) ?>" autocomplete="off" data-cerca-comuni required>
    <input type="hidden" name="comune_id" value="<?= $in['comune_id'] ?: '' ?>"><datalist id="comuni-list"></datalist><small class="hint">Scrivi almeno 2 lettere e scegli dall'elenco</small>
  </label>
  <label>Data di nascita del contraente<input type="date" name="nascita" value="<?= e($in['nascita']) ?>" required></label>
  <label>Marca<select name="marca_id" data-marca required><option value="">Scegli…</option><?php foreach ($marche as $k => $v): ?><option value="<?= (int)$k ?>"<?= $in['marca_id'] === (int)$k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
  <label>Modello<select name="modello_id" data-modello required><option value="">Scegli…</option><?php foreach ($modelli as $k => $v): ?><option value="<?= (int)$k ?>"<?= $in['modello_id'] === (int)$k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
  <?php campi_calcolo($in); ?>
  <div class="actions"><button class="btn">Calcola e crea il preventivo</button><a class="btn btn--ghost" href="clienti.php?id=<?= $cid ?>">Annulla</a></div>
</form>
<?php pagina_fine();
