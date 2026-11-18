<?php
/* Anagrafica clienti: elenco, scheda, modifica, stato, storico preventivi ed email. */
declare(strict_types=1);
require __DIR__ . '/_init.php';
$me = Auth::richiedi();

$stati = Clienti::stati();
$statiById = array_column($stati, null, 'id');
$tag = fn($sid) => isset($statiById[$sid]) ? '<span class="tag tag--stato" style="--c:' . e($statiById[$sid]['colore']) . '">' . e($statiById[$sid]['nome']) . '</span>' : '–';

/* ---------- azioni ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $az = (string)($_POST['azione'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($az === 'stato' && $id) {
        $sid = (int)($_POST['stato_id'] ?? 0);
        if (isset($statiById[$sid])) {
            db()->prepare('UPDATE clienti SET stato_id = ?, aggiornato_il = UTC_TIMESTAMP() WHERE id = ?')->execute([$sid, $id]);
            Auth::log('stato', 'clienti', $id, $statiById[$sid]['nome']);
            flash('ok', 'Stato aggiornato: ' . $statiById[$sid]['nome'] . '.');
        }
        vai('clienti.php?id=' . $id);
    }
    if ($az === 'elimina' && $id) {
        db()->prepare('DELETE FROM clienti WHERE id = ?')->execute([$id]);
        Auth::log('elimina', 'clienti', $id, 'con i suoi preventivi');
        flash('ok', 'Cliente eliminato insieme ai suoi preventivi.');
        vai('clienti.php');
    }
    if ($az === 'salva') {
        $v = [
            'nome' => trim(mb_substr((string)($_POST['nome'] ?? ''), 0, 120)),
            'email' => mb_strtolower(trim(mb_substr((string)($_POST['email'] ?? ''), 0, 190))) ?: null,
            'telefono' => trim(mb_substr((string)($_POST['telefono'] ?? ''), 0, 40)) ?: null,
            'codice_fiscale' => mb_strtoupper(trim((string)($_POST['codice_fiscale'] ?? ''))) ?: null,
            'data_nascita' => trim((string)($_POST['data_nascita'] ?? '')) ?: null,
            'comune_id' => (int)($_POST['comune_id'] ?? 0) ?: null,
            'indirizzo' => trim(mb_substr((string)($_POST['indirizzo'] ?? ''), 0, 190)) ?: null,
            'note' => trim(mb_substr((string)($_POST['note'] ?? ''), 0, 5000)) ?: null,
            'stato_id' => (int)($_POST['stato_id'] ?? 0),
            'consenso_privacy' => !empty($_POST['consenso_privacy']) ? 1 : 0,
        ];
        $err = [];
        if ($v['nome'] === '') $err[] = 'Scrivi nome e cognome (o la ragione sociale).';
        if ($v['email'] && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Email non valida.';
        if ($v['telefono'] && !preg_match('/^[0-9 +().\/-]{6,40}$/', $v['telefono'])) $err[] = 'Telefono non valido.';
        if ($v['codice_fiscale'] && !preg_match('/^([A-Z0-9]{16}|[0-9]{11})$/', $v['codice_fiscale'])) $err[] = 'Il codice fiscale deve avere 16 caratteri (o 11 cifre per le aziende).';
        if ($v['data_nascita'] && eta_da_data($v['data_nascita']) === null) $err[] = 'Data di nascita non valida.';
        if (!isset($statiById[$v['stato_id']])) $err[] = 'Scegli lo stato.';
        if ($v['email'] && !$err) {
            $st = db()->prepare('SELECT id FROM clienti WHERE LOWER(email) = ? AND id <> ?');
            $st->execute([$v['email'], $id]);
            if ($dup = (int)$st->fetchColumn()) $err[] = "Esiste già un cliente con questa email (scheda n. $dup).";
        }
        if (!$err) {
            if ($id) {
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($v)));
                db()->prepare("UPDATE clienti SET $set, aggiornato_il = UTC_TIMESTAMP() WHERE id = ?")->execute([...array_values($v), $id]);
                Auth::log('modifica', 'clienti', $id);
            } else {
                $v['origine'] = 'backoffice';
                $cols = implode(', ', array_keys($v));
                db()->prepare("INSERT INTO clienti ($cols, creato_il, aggiornato_il) VALUES (" . implode(', ', array_fill(0, count($v), '?')) . ', UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute(array_values($v));
                $id = (int)db()->lastInsertId();
                Auth::log('crea', 'clienti', $id);
            }
            flash('ok', 'Cliente salvato.');
            vai('clienti.php?id=' . $id);
        }
        foreach ($err as $m) flash('errore', $m);
        $form = $v + ['id' => $id];
    }
}

/* ---------- filtri ed export ---------- */
$par = []; $w = ['1=1'];
if (($q = trim((string)($_GET['q'] ?? ''))) !== '') { $w[] = '(c.nome LIKE ? OR c.email LIKE ? OR c.telefono LIKE ? OR c.codice_fiscale LIKE ?)'; array_push($par, "%$q%", "%$q%", "%$q%", "%$q%"); }
if (($fs = (int)($_GET['stato'] ?? 0)) > 0) { $w[] = 'c.stato_id = ?'; $par[] = $fs; }
$where = implode(' AND ', $w);
$qs = array_filter(['q' => $q, 'stato' => $fs ?: '']);

if (isset($_GET['esporta'])) {
    $st = db()->prepare("SELECT c.id, c.nome, c.email, c.telefono, c.codice_fiscale, c.data_nascita, CONCAT(co.nome, ' (', co.sigla, ')') AS comune, c.indirizzo, s.nome AS stato, c.origine, c.creato_il, c.note
        FROM clienti c LEFT JOIN comuni co ON co.id = c.comune_id JOIN stati_cliente s ON s.id = c.stato_id WHERE $where ORDER BY c.nome");
    $st->execute($par);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clienti-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['n', 'nome', 'email', 'telefono', 'codice_fiscale', 'nascita', 'comune', 'indirizzo', 'stato', 'origine', 'creato', 'note'], ';', '"', '');
    while ($r = $st->fetch()) { $r['creato_il'] = data_it($r['creato_il']); fputcsv($o, $r, ';', '"', ''); }
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
pagina_inizio('Clienti', 'clienti');

/* ---------- modulo nuovo / modifica ---------- */
if (isset($form) || isset($_GET['nuovo']) || ($id && isset($_GET['modifica']))):
    if (!isset($form)) {
        if ($id) { $st = db()->prepare('SELECT * FROM clienti WHERE id = ?'); $st->execute([$id]); $form = $st->fetch() ?: vai('clienti.php'); }
        else $form = ['id' => 0, 'stato_id' => Clienti::stato('nuovo'), 'consenso_privacy' => 0];
    }
    $comuneTxt = '';
    if (!empty($form['comune_id'])) { $st = db()->prepare('SELECT CONCAT(nome, \' (\', sigla, \')\') FROM comuni WHERE id = ?'); $st->execute([$form['comune_id']]); $comuneTxt = (string)$st->fetchColumn(); }
    $fid = (int)($form['id'] ?? 0); ?>
  <p class="crumbs"><a href="clienti.php<?= $fid ? '?id=' . $fid : '' ?>">← <?= $fid ? 'Scheda cliente' : 'Clienti' ?></a></p>
  <h1><?= $fid ? 'Modifica cliente' : 'Nuovo cliente' ?></h1>
  <form method="post" class="form form--grid" autocomplete="off">
    <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="salva"><input type="hidden" name="id" value="<?= $fid ?>">
    <label>Nome e cognome / ragione sociale *<input name="nome" required maxlength="120" value="<?= e($form['nome'] ?? '') ?>"></label>
    <label>Stato<select name="stato_id"><?php foreach ($stati as $s): if (!$s['attivo'] && (int)$s['id'] !== (int)$form['stato_id']) continue; ?><option value="<?= (int)$s['id'] ?>"<?= (int)$s['id'] === (int)$form['stato_id'] ? ' selected' : '' ?>><?= e($s['nome']) ?></option><?php endforeach; ?></select></label>
    <label>Email<input type="email" name="email" maxlength="190" value="<?= e($form['email'] ?? '') ?>"></label>
    <label>Telefono<input type="tel" name="telefono" maxlength="40" value="<?= e($form['telefono'] ?? '') ?>"></label>
    <label>Codice fiscale / P. IVA<input name="codice_fiscale" maxlength="16" value="<?= e($form['codice_fiscale'] ?? '') ?>"></label>
    <label>Data di nascita<input type="date" name="data_nascita" value="<?= e($form['data_nascita'] ?? '') ?>"></label>
    <label>Comune di residenza
      <input name="comune" list="comuni-list" value="<?= e($comuneTxt) ?>" autocomplete="off" data-cerca-comuni placeholder="Scrivi almeno 2 lettere">
      <input type="hidden" name="comune_id" value="<?= (int)($form['comune_id'] ?? 0) ?: '' ?>"><datalist id="comuni-list"></datalist>
    </label>
    <label>Indirizzo<input name="indirizzo" maxlength="190" value="<?= e($form['indirizzo'] ?? '') ?>"></label>
    <label class="full">Note interne<textarea name="note" rows="4"><?= e($form['note'] ?? '') ?></textarea></label>
    <div class="field field--check"><label><input type="checkbox" name="consenso_privacy" value="1"<?= !empty($form['consenso_privacy']) ? ' checked' : '' ?>> Il cliente ha dato il consenso al trattamento dei dati</label></div>
    <div class="actions"><button class="btn">Salva</button><a class="btn btn--ghost" href="clienti.php<?= $fid ? '?id=' . $fid : '' ?>">Annulla</a></div>
  </form>
<?php
/* ---------- scheda ---------- */
elseif ($id):
    $st = db()->prepare('SELECT c.*, CONCAT(co.nome, \' (\', co.sigla, \')\') AS comune FROM clienti c LEFT JOIN comuni co ON co.id = c.comune_id WHERE c.id = ?');
    $st->execute([$id]);
    $c = $st->fetch() ?: vai('clienti.php');
    $st = db()->prepare('SELECT id, creato_il, origine, veicolo, totale, totale_definitivo, stato, inviato_il, accettato_il FROM preventivi WHERE cliente_id = ? ORDER BY id DESC');
    $st->execute([$id]);
    $prev = $st->fetchAll();
    $st = db()->prepare('SELECT creato_il, tipo, destinatario, oggetto, esito, errore FROM email_inviate WHERE cliente_id = ? ORDER BY id DESC LIMIT 20');
    $st->execute([$id]);
    $mail = $st->fetchAll(); ?>
  <p class="crumbs"><a href="clienti.php">← Clienti</a></p>
  <div class="head">
    <h1><?= e($c['nome']) ?></h1>
    <div class="head__actions">
      <a class="btn btn--ghost" href="clienti.php?id=<?= $id ?>&amp;modifica=1">Modifica dati</a>
      <a class="btn" href="preventivo-nuovo.php?cliente=<?= $id ?>">+ Nuovo preventivo</a>
    </div>
  </div>
  <form method="post" class="form form--inline statobar">
    <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="stato"><input type="hidden" name="id" value="<?= $id ?>">
    <label>Stato del cliente
      <select name="stato_id" data-auto-submit><?php foreach ($stati as $s): if (!$s['attivo'] && (int)$s['id'] !== (int)$c['stato_id']) continue; ?><option value="<?= (int)$s['id'] ?>"<?= (int)$s['id'] === (int)$c['stato_id'] ? ' selected' : '' ?>><?= e($s['nome']) ?></option><?php endforeach; ?></select>
    </label>
    <button class="btn btn--ghost nojs">Aggiorna</button>
    <span class="muted">Aggiornato il <?= e(data_it($c['aggiornato_il'])) ?></span>
  </form>
  <div class="cols">
    <section class="box">
      <h2>Anagrafica</h2>
      <dl class="dl">
        <dt>Email</dt><dd><?= $c['email'] ? '<a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '–' ?></dd>
        <dt>Telefono</dt><dd><?= $c['telefono'] ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $c['telefono'])) . '">' . e($c['telefono']) . '</a>' : '–' ?></dd>
        <dt>Cod. fiscale</dt><dd><?= e($c['codice_fiscale'] ?: '–') ?></dd>
        <dt>Nascita</dt><dd><?= $c['data_nascita'] ? e(date('d/m/Y', strtotime($c['data_nascita']))) . ' (' . eta_da_data($c['data_nascita']) . ' anni)' : '–' ?></dd>
        <dt>Comune</dt><dd><?= e($c['comune'] ?: '–') ?></dd>
        <dt>Indirizzo</dt><dd><?= e($c['indirizzo'] ?: '–') ?></dd>
        <dt>Origine</dt><dd><?= $c['origine'] === 'sito' ? 'Richiesta dal sito' : 'Inserito in agenzia' ?>, <?= e(data_it($c['creato_il'], false)) ?></dd>
        <dt>Privacy</dt><dd><?= $c['consenso_privacy'] ? 'Consenso dato' : '<span class="muted">Non registrato</span>' ?></dd>
      </dl>
      <?php if ($c['note']): ?><h3>Note interne</h3><p class="pre"><?= e($c['note']) ?></p><?php endif; ?>
    </section>
    <section class="box">
      <h2>Preventivi <span class="muted">(<?= count($prev) ?>)</span></h2>
      <?php if (!$prev): ?><p class="muted">Nessun preventivo. <a href="preventivo-nuovo.php?cliente=<?= $id ?>">Creane uno</a>.</p><?php else: ?>
      <table class="table"><tbody>
      <?php foreach ($prev as $p): ?>
        <tr><td><a href="preventivi.php?id=<?= (int)$p['id'] ?>">N. <?= (int)$p['id'] ?></a> · <?= e(data_it($p['creato_il'], false)) ?><br><small class="muted"><?= e($p['veicolo']) ?> · <?= $p['origine'] === 'sito' ? 'dal sito' : 'in agenzia' ?></small></td>
        <td class="right"><?= euro($p['totale_definitivo'] ?? $p['totale']) ?><br><?= stato_preventivo($p['stato']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table><?php endif; ?>
      <h3>Email</h3>
      <?php if (!$mail): ?><p class="muted">Nessuna email inviata.</p><?php else: ?>
      <ul class="log"><?php foreach ($mail as $m): ?><li><span class="muted"><?= e(data_it($m['creato_il'])) ?></span> <?= e($m['oggetto']) ?> → <?= e($m['destinatario']) ?> <?= $m['esito'] === 'inviata' ? '<span class="tag tag--ok">inviata</span>' : '<span class="tag tag--nuovo" title="' . e((string)$m['errore']) . '">errore</span>' ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
  </div>
  <form method="post" class="danger" data-conferma="Eliminare il cliente e TUTTI i suoi preventivi? L'operazione non si può annullare.">
    <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $id ?>">
    <button class="btn btn--danger">Elimina cliente e preventivi</button>
    <small>Usalo per le richieste di cancellazione dei dati (diritto all'oblio).</small>
  </form>
<?php
/* ---------- elenco ---------- */
else:
    $per = 50; $pag = max(1, (int)($_GET['p'] ?? 1));
    $st = db()->prepare("SELECT COUNT(*) FROM clienti c WHERE $where"); $st->execute($par); $tot = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT c.id, c.nome, c.email, c.telefono, c.stato_id, c.origine, c.aggiornato_il, CONCAT(co.nome, ' (', co.sigla, ')') AS comune,
        (SELECT COUNT(*) FROM preventivi p WHERE p.cliente_id = c.id) AS n_prev
        FROM clienti c LEFT JOIN comuni co ON co.id = c.comune_id WHERE $where ORDER BY c.aggiornato_il DESC LIMIT $per OFFSET " . (($pag - 1) * $per));
    $st->execute($par);
    $conta = db()->query('SELECT stato_id, COUNT(*) FROM clienti GROUP BY stato_id')->fetchAll(PDO::FETCH_KEY_PAIR); ?>
  <div class="head">
    <h1>Clienti <span class="muted">(<?= $tot ?>)</span></h1>
    <div class="head__actions"><a class="btn btn--ghost" href="?<?= e(http_build_query($qs + ['esporta' => 1])) ?>">Esporta CSV</a><a class="btn" href="?nuovo=1">+ Nuovo cliente</a></div>
  </div>
  <p class="intro">Chi chiede un preventivo dal sito entra qui in automatico (stessa email = stesso cliente). Puoi aggiungere clienti a mano e fare i preventivi dal pannello.</p>
  <nav class="chips" aria-label="Filtra per stato">
    <a href="clienti.php"<?= !$fs ? ' aria-current="page"' : '' ?>>Tutti</a>
    <?php foreach ($stati as $s): ?><a href="?stato=<?= (int)$s['id'] ?>"<?= $fs === (int)$s['id'] ? ' aria-current="page"' : '' ?> style="--c:<?= e($s['colore']) ?>"><?= e($s['nome']) ?> <span><?= (int)($conta[$s['id']] ?? 0) ?></span></a><?php endforeach; ?>
  </nav>
  <form method="get" class="filters">
    <?php if ($fs): ?><input type="hidden" name="stato" value="<?= $fs ?>"><?php endif; ?>
    <input type="search" name="q" placeholder="Nome, email, telefono, codice fiscale…" value="<?= e($q) ?>" aria-label="Cerca">
    <button class="btn btn--ghost">Cerca</button>
  </form>
  <div class="tablewrap"><table class="table">
    <thead><tr><th>Cliente</th><th>Contatti</th><th>Comune</th><th>Stato</th><th class="right">Preventivi</th><th>Aggiornato</th></tr></thead>
    <tbody>
    <?php foreach ($st as $c): ?>
      <tr><td><a href="?id=<?= (int)$c['id'] ?>"><b><?= e($c['nome']) ?></b></a><?= $c['origine'] === 'sito' ? ' <small class="muted">dal sito</small>' : '' ?></td>
      <td><?= e($c['email'] ?: '') ?><br><small class="muted"><?= e($c['telefono'] ?: '') ?></small></td>
      <td><?= e($c['comune'] ?: '–') ?></td><td><?= $tag($c['stato_id']) ?></td><td class="right"><?= (int)$c['n_prev'] ?></td><td><?= e(data_it($c['aggiornato_il'], false)) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$tot): ?><tr><td colspan="6" class="muted">Nessun cliente.</td></tr><?php endif; ?>
    </tbody></table></div>
  <?= paginazione($tot, $pag, $per, $qs) ?>
<?php endif;
pagina_fine();
