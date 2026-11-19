<?php
/* Polizze stipulate: scadenziario generale, scheda della polizza, nuova polizza (anche dai dati letti da un contratto). */
declare(strict_types=1);
require __DIR__ . '/_init.php';
$me = Auth::richiedi();
$cfg = Avvisi::config();
$oggi = date('Y-m-d');

function polizza(int $id): ?array
{
    $st = db()->prepare('SELECT p.*, c.nome AS cliente, c.email AS email_cliente, co.nome AS compagnia, co.portale_url, pr.nome AS prodotto
        FROM polizze p JOIN clienti c ON c.id = p.cliente_id JOIN compagnie co ON co.id = p.compagnia_id LEFT JOIN prodotti pr ON pr.id = p.prodotto_id WHERE p.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* ---------- azioni ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $az = (string)($_POST['azione'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($az === 'salva') {
        $v = [
            'cliente_id' => (int)($_POST['cliente_id'] ?? 0),
            'compagnia_id' => (int)($_POST['compagnia_id'] ?? 0),
            'prodotto_id' => (int)($_POST['prodotto_id'] ?? 0) ?: null,
            'categoria_id' => (int)($_POST['categoria_id'] ?? 0) ?: null,
            'numero' => trim(mb_substr((string)($_POST['numero'] ?? ''), 0, 60)) ?: null,
            'oggetto' => trim(mb_substr((string)($_POST['oggetto'] ?? ''), 0, 190)) ?: null,
            'premio_annuo' => num((string)($_POST['premio_annuo'] ?? '')),
            'frazionamento' => (string)($_POST['frazionamento'] ?? 'annuale'),
            'data_stipula' => trim((string)($_POST['data_stipula'] ?? '')) ?: null,
            'scadenza' => trim((string)($_POST['scadenza'] ?? '')),
            'avvisa_cliente' => !empty($_POST['avvisa_cliente']) ? 1 : 0,
            'stato' => (string)($_POST['stato'] ?? 'attiva'),
            'note' => trim(mb_substr((string)($_POST['note'] ?? ''), 0, 5000)) ?: null,
        ];
        $err = [];
        $ok = fn($d) => $d && DateTimeImmutable::createFromFormat('!Y-m-d', $d) && DateTimeImmutable::createFromFormat('!Y-m-d', $d)->format('Y-m-d') === $d;
        $st = db()->prepare('SELECT COUNT(*) FROM clienti WHERE id = ?'); $st->execute([$v['cliente_id']]);
        if (!$st->fetchColumn()) $err[] = 'Cliente non valido.';
        $st = db()->prepare('SELECT COUNT(*) FROM compagnie WHERE id = ?'); $st->execute([$v['compagnia_id']]);
        if (!$st->fetchColumn()) $err[] = 'Scegli la compagnia.';
        if ($v['prodotto_id']) {
            $st = db()->prepare('SELECT compagnia_id, categoria_id FROM prodotti WHERE id = ?'); $st->execute([$v['prodotto_id']]); $pr = $st->fetch();
            if (!$pr || (int)$pr['compagnia_id'] !== $v['compagnia_id']) $err[] = 'Il prodotto non appartiene alla compagnia scelta.';
            elseif (!$v['categoria_id']) $v['categoria_id'] = $pr['categoria_id'] ? (int)$pr['categoria_id'] : null;
        }
        $nuovoProd = trim(mb_substr((string)($_POST['prodotto_nuovo'] ?? ''), 0, 120));
        if (!$err && !$v['prodotto_id'] && $nuovoProd !== '' && $v['compagnia_id']) {
            // nuovo prodotto aggiunto al catalogo della compagnia (o ritrovato se esiste già)
            db()->prepare('INSERT INTO prodotti (compagnia_id, nome, categoria_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), categoria_id = COALESCE(categoria_id, VALUES(categoria_id))')
                ->execute([$v['compagnia_id'], $nuovoProd, $v['categoria_id']]);
            $v['prodotto_id'] = (int)db()->lastInsertId();
            Auth::log('crea', 'prodotti', $v['prodotto_id'], $nuovoProd);
        }
        if (!$v['categoria_id']) $err[] = 'Scegli la classificazione della polizza (area e categoria).';
        if (!isset(Scadenze::MESI[$v['frazionamento']])) $err[] = 'Frazionamento non valido.';
        if (!isset(Scadenze::STATI[$v['stato']])) $v['stato'] = 'attiva';
        if (!$ok($v['scadenza'])) $err[] = 'Inserisci la scadenza dell\'annualità.';
        if ($v['data_stipula'] && !$ok($v['data_stipula'])) $err[] = 'Data di stipula non valida.';
        if (!$err && $v['data_stipula'] && $v['data_stipula'] >= $v['scadenza']) $err[] = 'La scadenza deve essere dopo la data di stipula.';
        if (($_POST['premio_annuo'] ?? '') !== '' && ($v['premio_annuo'] === null || $v['premio_annuo'] < 0)) $err[] = 'Premio non valido.';
        if (!$err) {
            if ($id) {
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($v)));
                db()->prepare("UPDATE polizze SET $set, aggiornato_il = UTC_TIMESTAMP() WHERE id = ?")->execute([...array_values($v), $id]);
                Auth::log('modifica', 'polizze', $id);
            } else {
                $cols = implode(', ', array_keys($v));
                db()->prepare("INSERT INTO polizze ($cols, creato_il, aggiornato_il) VALUES (" . implode(', ', array_fill(0, count($v), '?')) . ', UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute(array_values($v));
                $id = (int)db()->lastInsertId();
                Auth::log('crea', 'polizze', $id);
            }
            if ($doc = (int)($_POST['documento_id'] ?? 0)) {
                db()->prepare('UPDATE documenti SET polizza_id = ? WHERE id = ? AND cliente_id = ?')->execute([$id, $doc, $v['cliente_id']]);
                unset($_SESSION['lettura'][$doc]);
            }
            flash('ok', 'Polizza salvata.');
            vai('polizze.php?id=' . $id);
        }
        foreach ($err as $m) flash('errore', $m);
        $form = $v + ['id' => $id, 'documento_id' => (int)($_POST['documento_id'] ?? 0), 'prodotto_nuovo' => $nuovoProd ?? ''];
    }
    if ($id && ($p = polizza($id))) {
        if ($az === 'rinnova') {
            $nuova = Scadenze::aggiungiMesi(new DateTimeImmutable($p['scadenza']), 12)->format('Y-m-d');
            db()->prepare("UPDATE polizze SET scadenza = ?, stato = 'attiva', aggiornato_il = UTC_TIMESTAMP() WHERE id = ?")->execute([$nuova, $id]);
            Auth::log('rinnova', 'polizze', $id, $p['scadenza'] . ' → ' . $nuova);
            flash('ok', 'Polizza rinnovata: nuova scadenza ' . date('d/m/Y', strtotime($nuova)) . '.');
            vai('polizze.php?id=' . $id);
        }
        if ($az === 'elimina') {
            db()->prepare('DELETE FROM polizze WHERE id = ?')->execute([$id]);
            Auth::log('elimina', 'polizze', $id);
            flash('ok', 'Polizza eliminata (i documenti restano nella scheda del cliente).');
            vai('clienti.php?id=' . $p['cliente_id']);
        }
    }
}

pagina_inizio('Polizze', 'polizze');
$compagnie = db()->query('SELECT id, nome, attivo FROM compagnie ORDER BY nome')->fetchAll();

/* ---------- modulo nuova / modifica ---------- */
if (isset($form) || isset($_GET['nuovo']) || isset($_GET['modifica'])):
    $lettura = null; $auto = [];
    $docId = (int)($_GET['doc'] ?? $form['documento_id'] ?? 0);
    if (!isset($form)) {
        if (isset($_GET['modifica'])) { $form = polizza((int)$_GET['modifica']) ?? vai('polizze.php'); }
        else {
            $form = ['id' => 0, 'cliente_id' => (int)($_GET['cliente'] ?? 0), 'frazionamento' => 'annuale', 'avvisa_cliente' => 1, 'stato' => 'attiva', 'compagnia_id' => 0, 'prodotto_id' => null];
            if ($pv = (int)($_GET['preventivo'] ?? 0)) {
                $st = db()->prepare('SELECT p.cliente_id, p.veicolo, p.compagnia, p.decorrenza, COALESCE(p.totale_definitivo, p.totale) AS premio, p.prodotto_id, p.frazionamento, pr.compagnia_id AS pr_compagnia, pr.categoria_id AS pr_categoria FROM preventivi p LEFT JOIN prodotti pr ON pr.id = p.prodotto_id WHERE p.id = ?'); $st->execute([$pv]);
                if ($x = $st->fetch()) {
                    $form['cliente_id'] = (int)$x['cliente_id']; $form['oggetto'] = $x['veicolo']; $form['premio_annuo'] = $x['premio'];
                    if ($x['decorrenza']) { $form['data_stipula'] = $x['decorrenza']; $form['scadenza'] = Scadenze::scadenzaDaStipula($x['decorrenza']); }
                    foreach ($compagnie as $co) if ($x['compagnia'] && mb_strtolower($co['nome']) === mb_strtolower(trim($x['compagnia']))) $form['compagnia_id'] = (int)$co['id'];
                    if ($x['prodotto_id'] && $x['pr_compagnia'] && (!$form['compagnia_id'] || $form['compagnia_id'] === (int)$x['pr_compagnia'])) {
                        $form['compagnia_id'] = (int)$x['pr_compagnia']; $form['prodotto_id'] = (int)$x['prodotto_id'];
                        if ($x['pr_categoria']) $form['categoria_id'] = (int)$x['pr_categoria'];
                    }
                    if ($x['frazionamento'] && isset(Scadenze::MESI[$x['frazionamento']])) $form['frazionamento'] = $x['frazionamento'];
                }
            }
        }
        if ($docId && isset($_SESSION['lettura'][$docId])) {
            $lettura = $_SESSION['lettura'][$docId];
            foreach ($lettura['trovati'] as $k => $val) {
                if ($k === 'categoria') { if ($cid = Categorie::perChiave($val)) { $form['categoria_id'] = $cid; $auto['categoria_id'] = true; } continue; }
                $form[$k] = $val; $auto[$k] = true;
            }
            if (!empty($form['prodotto_id'])) {
                $st = db()->prepare('SELECT categoria_id FROM prodotti WHERE id = ?'); $st->execute([(int)$form['prodotto_id']]);
                if ($c = $st->fetchColumn()) { $form['categoria_id'] = (int)$c; $auto['categoria_id'] = true; }
            }
        }
    }
    $st = db()->prepare('SELECT id, nome FROM clienti WHERE id = ?'); $st->execute([(int)$form['cliente_id']]);
    $cli = $st->fetch() ?: vai('clienti.php');
    $prodotti = [];
    if (!empty($form['compagnia_id'])) { $st = db()->prepare('SELECT id, nome, categoria_id FROM prodotti WHERE compagnia_id = ? ORDER BY nome'); $st->execute([(int)$form['compagnia_id']]); foreach ($st as $r) $prodotti[(int)$r['id']] = [$r['nome'], $r['categoria_id']]; }
    $a = fn($k) => isset($auto[$k]) ? ' class="auto" title="Letto dal contratto: controlla"' : '';
    $fid = (int)($form['id'] ?? 0); ?>
  <p class="crumbs"><a href="clienti.php?id=<?= (int)$cli['id'] ?>">← <?= e($cli['nome']) ?></a></p>
  <h1><?= $fid ? 'Modifica polizza' : 'Nuova polizza' ?> <span class="muted">· <?= e($cli['nome']) ?></span></h1>
  <?php if ($lettura): ?>
    <div class="flash flash--<?= $lettura['trovati'] ? 'info' : 'errore' ?>">
      <?php if ($lettura['trovati']): ?><b>Ho letto il contratto e compilato <?= count($lettura['trovati']) ?> campi</b> (evidenziati in giallo). Controllali prima di salvare.<?php endif; ?>
      <?php foreach ($lettura['note'] as $n): ?><br><?= e($n) ?><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <form method="post" class="form form--grid" id="polizza" data-giorni-agenzia="<?= (int)$cfg['giorni_agenzia'] ?>" data-giorni-cliente="<?= (int)$cfg['giorni_cliente'] ?>" data-cliente-attivo="<?= $cfg['cliente'] ? 1 : 0 ?>">
    <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="salva"><input type="hidden" name="id" value="<?= $fid ?>">
    <input type="hidden" name="cliente_id" value="<?= (int)$cli['id'] ?>"><input type="hidden" name="documento_id" value="<?= $docId ?: '' ?>">
    <label<?= $a('compagnia_id') ?>>Compagnia *<select name="compagnia_id" data-compagnia required><option value="">Scegli…</option><?php foreach ($compagnie as $co): if (!$co['attivo'] && (int)$co['id'] !== (int)$form['compagnia_id']) continue; ?><option value="<?= (int)$co['id'] ?>"<?= (int)$co['id'] === (int)$form['compagnia_id'] ? ' selected' : '' ?>><?= e($co['nome']) ?></option><?php endforeach; ?></select></label>
    <label<?= $a('prodotto_id') ?>>Prodotto<select name="prodotto_id" data-prodotto><option value="">–</option><?php foreach ($prodotti as $k => [$n, $cat]): ?><option value="<?= (int)$k ?>" data-categoria="<?= (int)$cat ?>"<?= (int)$k === (int)($form['prodotto_id'] ?? 0) ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select><small><a href="tabella.php?t=prodotti&amp;nuovo=1" target="_blank">Aggiungi un prodotto al catalogo ↗</a></small></label>
    <label<?= $a('prodotto_nuovo') ?>>…oppure nuovo prodotto<input name="prodotto_nuovo" maxlength="120" value="<?= e((string)($form['prodotto_nuovo'] ?? '')) ?>" placeholder="Nome commerciale, es. Allianz Bonus Malus"><small>Se non è nel catalogo lo aggiungo io alla compagnia scelta.</small></label>
    <label<?= $a('categoria_id') ?>>Classificazione *<select name="categoria_id" data-categoria required><option value="">Scegli area e categoria…</option><?= Categorie::opzioni(!empty($form['categoria_id']) ? (int)$form['categoria_id'] : null) ?></select><small>Si compila da sola quando scegli un prodotto già classificato.</small></label>
    <label<?= $a('numero') ?>>Numero di polizza<input name="numero" maxlength="60" value="<?= e((string)($form['numero'] ?? '')) ?>"></label>
    <label<?= $a('oggetto') ?>>Oggetto assicurato<input name="oggetto" maxlength="190" value="<?= e((string)($form['oggetto'] ?? '')) ?>" placeholder="Es. Fiat Panda targa GH123KL, abitazione di via…"></label>
    <label<?= $a('premio_annuo') ?>>Premio annuo (€)<input name="premio_annuo" inputmode="decimal" value="<?= isset($form['premio_annuo']) && $form['premio_annuo'] !== null && $form['premio_annuo'] !== '' ? e(number_format((float)$form['premio_annuo'], 2, ',', '')) : '' ?>"></label>
    <label<?= $a('frazionamento') ?>>Tipo di scadenza (frazionamento) *<select name="frazionamento" data-fraz><?php foreach (Scadenze::NOMI as $k => $n): ?><option value="<?= $k ?>"<?= ($form['frazionamento'] ?? '') === $k ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></label>
    <label<?= $a('data_stipula') ?>>Data di stipula / effetto<input type="date" name="data_stipula" data-stipula value="<?= e((string)($form['data_stipula'] ?? '')) ?>"></label>
    <label<?= $a('scadenza') ?>>Scadenza dell'annualità *<input type="date" name="scadenza" data-scadenza required value="<?= e((string)($form['scadenza'] ?? '')) ?>"><small>Se lasci vuoto e indichi la stipula, propongo un anno dopo.</small></label>
    <div class="calc full" aria-live="polite">
      <div><span>Prossima scadenza</span><b data-out="prossima">–</b></div>
      <div><span>Avviso all'agenzia il</span><b data-out="agenzia">–</b></div>
      <div><span>Avviso al cliente il</span><b data-out="cliente">–</b></div>
      <small>Calcolati in automatico (non modificabili) da scadenza, frazionamento e giorni di preavviso impostati in <a href="impostazioni.php#avvisi">Impostazioni</a>.</small>
    </div>
    <div class="field field--check full"><label><input type="checkbox" name="avvisa_cliente" value="1"<?= !empty($form['avvisa_cliente']) ? ' checked' : '' ?>> Alla scadenza comunica la scadenza al cliente via email</label>
      <?php if (!$cfg['cliente']): ?><small>Ora gli avvisi vanno solo all'agenzia: per inviarli anche ai clienti cambia l'impostazione "Destinatari" in <a href="impostazioni.php#avvisi">Impostazioni</a>.</small><?php endif; ?></div>
    <label>Stato<select name="stato"><?php foreach (Scadenze::STATI as $k => $n): ?><option value="<?= $k ?>"<?= ($form['stato'] ?? 'attiva') === $k ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></label>
    <label class="full">Note<textarea name="note" rows="3"><?= e((string)($form['note'] ?? '')) ?></textarea></label>
    <div class="actions"><button class="btn">Salva la polizza</button><a class="btn btn--ghost" href="<?= $fid ? 'polizze.php?id=' . $fid : 'clienti.php?id=' . (int)$cli['id'] ?>">Annulla</a></div>
  </form>
<?php
/* ---------- scheda della polizza ---------- */
elseif (isset($_GET['id'])):
    $p = polizza((int)$_GET['id']) ?? vai('polizze.php');
    $pros = $p['stato'] === 'attiva' ? Scadenze::prossima($p['scadenza'], $p['frazionamento']) : null;
    $st = db()->prepare('SELECT * FROM documenti WHERE polizza_id = ? ORDER BY id DESC'); $st->execute([$p['id']]); $docs = $st->fetchAll();
    $st = db()->prepare('SELECT * FROM avvisi_scadenza WHERE polizza_id = ? ORDER BY id DESC LIMIT 30'); $st->execute([$p['id']]); $avv = $st->fetchAll();
    if (isset($_GET['doc'], $_SESSION['lettura'][(int)$_GET['doc']])) vai('polizze.php?modifica=' . (int)$p['id'] . '&doc=' . (int)$_GET['doc']); ?>
  <p class="crumbs"><a href="polizze.php">← Scadenziario</a> · <a href="clienti.php?id=<?= (int)$p['cliente_id'] ?>">Scheda di <?= e($p['cliente']) ?></a></p>
  <div class="head">
    <h1><?= e($p['prodotto'] ?: 'Polizza') ?> · <?= e($p['compagnia']) ?> <span class="tag tag--pol-<?= e($p['stato']) ?>"><?= e(Scadenze::STATI[$p['stato']]) ?></span></h1>
    <div class="head__actions">
      <a class="btn btn--ghost" href="polizze.php?modifica=<?= (int)$p['id'] ?>">Modifica</a>
      <form method="post" class="inline" data-conferma="Rinnovare la polizza per un altro anno? La scadenza passa al <?= e(date('d/m/Y', strtotime(Scadenze::aggiungiMesi(new DateTimeImmutable($p['scadenza']), 12)->format('Y-m-d')))) ?>.">
        <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="rinnova"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn">Rinnova di un anno</button></form>
    </div>
  </div>
  <div class="cols">
    <section class="box">
      <h2>Dati della polizza</h2>
      <dl class="dl">
        <dt>Cliente</dt><dd><a href="clienti.php?id=<?= (int)$p['cliente_id'] ?>"><?= e($p['cliente']) ?></a></dd>
        <dt>Compagnia</dt><dd><?= e($p['compagnia']) ?><?= $p['portale_url'] ? ' · <a href="' . e($p['portale_url']) . '" target="_blank" rel="noopener">portale ↗</a>' : '' ?></dd>
        <dt>Prodotto</dt><dd><?= e($p['prodotto'] ?: '–') ?></dd>
        <dt>Classificazione</dt><dd><?= e(Categorie::etichetta($p['categoria_id'] ? (int)$p['categoria_id'] : null)) ?></dd>
        <dt>Numero</dt><dd><?= e($p['numero'] ?: '–') ?></dd>
        <dt>Oggetto</dt><dd><?= e($p['oggetto'] ?: '–') ?></dd>
        <dt>Premio annuo</dt><dd><?= euro($p['premio_annuo']) ?></dd>
        <dt>Frazionamento</dt><dd><?= e(Scadenze::NOMI[$p['frazionamento']]) ?></dd>
        <dt>Stipula</dt><dd><?= $p['data_stipula'] ? e(date('d/m/Y', strtotime($p['data_stipula']))) : '–' ?></dd>
        <dt>Scadenza</dt><dd><b><?= e(date('d/m/Y', strtotime($p['scadenza']))) ?></b><?= $p['scadenza'] < $oggi && $p['stato'] === 'attiva' ? ' <span class="tag tag--nuovo">passata: rinnova o cambia stato</span>' : '' ?></dd>
        <dt>Avviso cliente</dt><dd><?= $p['avvisa_cliente'] ? 'Sì, via email' . ($cfg['cliente'] ? '' : ' <small class="muted">(ora disattivato in Impostazioni)</small>') : 'No' ?></dd>
      </dl>
      <?php if ($p['note']): ?><h3>Note</h3><p class="pre"><?= e($p['note']) ?></p><?php endif; ?>
    </section>
    <section class="box">
      <h2>Scadenze dell'annualità</h2>
      <table class="table table--small"><thead><tr><th>Scadenza</th><th>Tipo</th><th>Avviso agenzia</th><th>Avviso cliente</th></tr></thead><tbody>
      <?php foreach (Scadenze::rate($p['scadenza'], $p['frazionamento']) as $d):
          $ann = $d === $p['scadenza']; ?>
        <tr<?= $pros && $pros['data'] === $d ? ' class="next"' : ($d < $oggi ? ' class="off"' : '') ?>><td><?= date('d/m/Y', strtotime($d)) ?></td><td><?= $ann ? 'Annualità' : 'Rata' ?></td>
        <td><?= !$ann && !$cfg['rate'] ? '<span class="muted">–</span>' : date('d/m/Y', strtotime(Scadenze::dataAvviso($d, $cfg['giorni_agenzia']))) ?></td>
        <td><?= (!$ann && !$cfg['rate']) || !$cfg['cliente'] || !$p['avvisa_cliente'] ? '<span class="muted">–</span>' : date('d/m/Y', strtotime(Scadenze::dataAvviso($d, $cfg['giorni_cliente']))) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
      <p class="muted">Gli avvisi partono dalle ore <?= (int)$cfg['ora'] ?>:00 del giorno indicato.</p>
      <h3>Avvisi inviati</h3>
      <?php if (!$avv): ?><p class="muted">Nessun avviso ancora.</p><?php else: ?>
      <ul class="log"><?php foreach ($avv as $x): ?><li><span class="muted"><?= e(data_it($x['creato_il'])) ?></span> <?= $x['evento'] === 'annualita' ? 'Annualità' : 'Rata' ?> del <?= date('d/m/Y', strtotime($x['data_evento'])) ?> → <?= e($x['destinatario']) ?><?= $x['email'] ? ' (' . e($x['email']) . ')' : '' ?> <?= $x['esito'] === 'inviata' ? '<span class="tag tag--ok">inviato</span>' : '<span class="tag tag--nuovo">' . e($x['esito']) . '</span> <small class="err">' . e((string)$x['errore']) . '</small>' ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
  </div>
  <section class="box" id="documenti">
    <h2>Documenti della polizza</h2>
    <?php docs_tabella($docs, (int)$p['id']); ?>
    <?php docs_form((int)$p['cliente_id'], (int)$p['id']); ?>
  </section>
  <form method="post" class="danger" data-conferma="Eliminare questa polizza? I documenti restano nella scheda del cliente.">
    <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <button class="btn btn--danger">Elimina la polizza</button><small>Per una polizza non rinnovata usa piuttosto lo stato "Disdetta" o "Scaduta": resta nello storico.</small>
  </form>
<?php
/* ---------- scadenziario ---------- */
else:
    $entro = (int)($_GET['entro'] ?? 60);
    $fc = (int)($_GET['compagnia'] ?? 0);
    $fa = (int)($_GET['area'] ?? 0);
    $ff = (string)($_GET['fraz'] ?? '');
    $q = trim((string)($_GET['q'] ?? ''));
    $tutte = isset($_GET['tutte']);
    $par = []; $w = ["p.stato = 'attiva'"];
    if ($fc) { $w[] = 'p.compagnia_id = ?'; $par[] = $fc; }
    if ($fa) { $w[] = 'p.categoria_id IN (SELECT x.id FROM categorie x WHERE x.id = ? OR x.parent_id = ?)'; $par[] = $fa; $par[] = $fa; }
    if (isset(Scadenze::MESI[$ff])) { $w[] = 'p.frazionamento = ?'; $par[] = $ff; }
    if ($q !== '') { $w[] = '(c.nome LIKE ? OR p.numero LIKE ? OR p.oggetto LIKE ?)'; array_push($par, "%$q%", "%$q%", "%$q%"); }
    $st = db()->prepare('SELECT p.*, c.nome AS cliente, c.telefono, c.email, co.nome AS compagnia, pr.nome AS prodotto FROM polizze p JOIN clienti c ON c.id = p.cliente_id
        JOIN compagnie co ON co.id = p.compagnia_id LEFT JOIN prodotti pr ON pr.id = p.prodotto_id WHERE ' . implode(' AND ', $w));
    $st->execute($par);
    $righe = [];
    $limite = (new DateTimeImmutable('today'))->modify("+$entro days")->format('Y-m-d');
    foreach ($st as $p) {
        $pr = Scadenze::prossima($p['scadenza'], $p['frazionamento']);
        $p['prossima'] = $pr['data'] ?? $p['scadenza'];
        $p['evento'] = $pr['evento'] ?? 'scaduta';
        if (!$tutte && $p['prossima'] > $limite) continue;
        $righe[] = $p;
    }
    usort($righe, fn($a, $b) => $a['prossima'] <=> $b['prossima']);
    $qs = array_filter(['entro' => $entro !== 60 ? $entro : '', 'compagnia' => $fc ?: '', 'area' => $fa ?: '', 'fraz' => $ff, 'q' => $q, 'tutte' => $tutte ? 1 : '']); ?>
  <div class="head"><h1>Scadenziario polizze <span class="muted">(<?= count($righe) ?>)</span></h1>
    <div class="head__actions"><a class="btn btn--ghost" href="tabella.php?t=compagnie">Compagnie</a><a class="btn btn--ghost" href="tabella.php?t=prodotti">Prodotti</a></div></div>
  <p class="intro">Tutte le polizze attive in ordine di prossima scadenza (rata o annualità), da tutte le compagnie. Le nuove polizze si aggiungono dalla scheda del cliente.</p>
  <nav class="chips" aria-label="Periodo">
    <?php foreach ([15 => 'Entro 15 giorni', 30 => 'Entro 30 giorni', 60 => 'Entro 60 giorni', 120 => 'Entro 4 mesi'] as $g => $l): ?><a href="?<?= e(http_build_query(array_merge($qs, ['entro' => $g, 'tutte' => '']))) ?>"<?= !$tutte && $entro === $g ? ' aria-current="page"' : '' ?>><?= $l ?></a><?php endforeach; ?>
    <a href="?<?= e(http_build_query(array_merge($qs, ['tutte' => 1]))) ?>"<?= $tutte ? ' aria-current="page"' : '' ?>>Tutte le attive</a>
  </nav>
  <form method="get" class="filters">
    <?php if ($tutte): ?><input type="hidden" name="tutte" value="1"><?php else: ?><input type="hidden" name="entro" value="<?= $entro ?>"><?php endif; ?>
    <input type="search" name="q" placeholder="Cliente, numero, targa…" value="<?= e($q) ?>" aria-label="Cerca">
    <select name="area" aria-label="Classificazione"><option value="">Auto, danni e vita</option><?= Categorie::opzioni($fa ?: null) ?></select>
    <select name="compagnia" aria-label="Compagnia"><option value="">Tutte le compagnie</option><?php foreach ($compagnie as $co): ?><option value="<?= (int)$co['id'] ?>"<?= $fc === (int)$co['id'] ? ' selected' : '' ?>><?= e($co['nome']) ?></option><?php endforeach; ?></select>
    <select name="fraz" aria-label="Frazionamento"><option value="">Ogni frazionamento</option><?php foreach (Scadenze::NOMI as $k => $n): ?><option value="<?= $k ?>"<?= $ff === $k ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
    <button class="btn btn--ghost">Filtra</button>
  </form>
  <div class="tablewrap"><table class="table">
    <thead><tr><th>Prossima scadenza</th><th>Cliente</th><th>Polizza</th><th>Frazionamento</th><th class="right">Premio annuo</th><th>Avviso cliente</th></tr></thead>
    <tbody>
    <?php foreach ($righe as $p):
        $gg = (int)(new DateTimeImmutable($oggi))->diff(new DateTimeImmutable($p['prossima']))->format('%r%a'); ?>
      <tr><td><b><?= date('d/m/Y', strtotime($p['prossima'])) ?></b><br><small class="<?= $gg < 0 ? 'err' : ($gg <= 15 ? 'warn' : 'muted') ?>"><?= $gg < 0 ? 'passata da ' . -$gg . ' gg · da rinnovare' : ($gg === 0 ? 'oggi' : "tra $gg giorni") ?> · <?= $p['evento'] === 'rata' ? 'rata' : 'annualità' ?></small></td>
      <td><a href="clienti.php?id=<?= (int)$p['cliente_id'] ?>"><?= e($p['cliente']) ?></a><br><small class="muted"><?= e((string)($p['telefono'] ?: $p['email'])) ?></small></td>
      <td><a href="?id=<?= (int)$p['id'] ?>"><?= e($p['prodotto'] ?: 'Polizza') ?></a> · <?= e($p['compagnia']) ?><br><small class="muted"><?= e(Categorie::etichetta($p['categoria_id'] ? (int)$p['categoria_id'] : null)) ?><?= $p['numero'] ? ' · n. ' . e($p['numero']) : '' ?></small></td>
      <td><?= e(Scadenze::NOMI[$p['frazionamento']]) ?></td><td class="right"><?= euro($p['premio_annuo']) ?></td><td><?= $p['avvisa_cliente'] ? 'Sì' : 'No' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$righe): ?><tr><td colspan="6" class="muted">Nessuna scadenza nel periodo.</td></tr><?php endif; ?>
    </tbody></table></div>
<?php endif;
pagina_fine();
