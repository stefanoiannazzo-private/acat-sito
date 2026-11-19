<?php
/* Anagrafica clienti (persone e aziende): elenco, scheda, modifica, legami, sedi, polizze per area, preventivi ed email. */
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
        $st = db()->prepare('SELECT id FROM documenti WHERE cliente_id = ?'); $st->execute([$id]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $did) Documenti::elimina((int)$did);
        db()->prepare('DELETE FROM clienti WHERE id = ?')->execute([$id]);
        Auth::log('elimina', 'clienti', $id, 'con i suoi preventivi');
        flash('ok', 'Cliente eliminato insieme ai suoi preventivi.');
        vai('clienti.php');
    }
    if ($az === 'salva') {
        $tipo = ($_POST['tipo'] ?? '') === 'azienda' ? 'azienda' : 'persona';
        if ($id) { $st = db()->prepare('SELECT tipo FROM clienti WHERE id = ?'); $st->execute([$id]); $tipo = (string)$st->fetchColumn() ?: $tipo; }
        $t = fn(string $k, int $max) => trim(mb_substr((string)($_POST[$k] ?? ''), 0, $max)) ?: null;
        $v = [
            'tipo' => $tipo,
            'nome_proprio' => $tipo === 'persona' ? $t('nome_proprio', 80) : null,
            'cognome' => $tipo === 'persona' ? $t('cognome', 80) : null,
            'email' => mb_strtolower((string)$t('email', 190)) ?: null,
            'pec' => $tipo === 'azienda' ? (mb_strtolower((string)$t('pec', 190)) ?: null) : null,
            'telefono' => $t('telefono', 40),
            'codice_fiscale' => mb_strtoupper((string)$t('codice_fiscale', 16)) ?: null,
            'partita_iva' => $tipo === 'azienda' ? (preg_replace('/\s+/', '', strtoupper((string)$t('partita_iva', 20))) ?: null) : null,
            'codice_sdi' => $tipo === 'azienda' ? (mb_strtoupper((string)$t('codice_sdi', 7)) ?: null) : null,
            'data_nascita' => $tipo === 'persona' ? $t('data_nascita', 10) : null,
            'comune_id' => $tipo === 'persona' ? ((int)($_POST['comune_id'] ?? 0) ?: null) : null,
            'indirizzo' => $t('indirizzo', 190),
            'cap' => $tipo === 'azienda' ? $t('cap', 10) : null,
            'citta' => $tipo === 'azienda' ? $t('citta', 100) : null,
            'provincia' => $tipo === 'azienda' ? (mb_strtoupper((string)$t('provincia', 60)) ?: null) : null,
            'nazione' => $tipo === 'azienda' ? ($t('nazione', 60) ?: 'Italia') : null,
            'note' => $t('note', 5000),
            'stato_id' => (int)($_POST['stato_id'] ?? 0),
            'consenso_privacy' => !empty($_POST['consenso_privacy']) ? 1 : 0,
        ];
        $v['nome'] = Clienti::nomeCompleto($tipo, (string)$v['nome_proprio'], $v['cognome'], $t('ragione_sociale', 120));
        $err = [];
        if ($tipo === 'persona' && (!$v['nome_proprio'] || !$v['cognome'])) $err[] = 'Scrivi nome e cognome.';
        if ($tipo === 'azienda' && $v['nome'] === '') $err[] = 'Scrivi la ragione sociale.';
        if (mb_strlen($v['nome']) > 120) $err[] = 'Nome troppo lungo.';
        foreach (['email' => 'Email', 'pec' => 'PEC'] as $k => $l) if ($v[$k] && !filter_var($v[$k], FILTER_VALIDATE_EMAIL)) $err[] = "$l non valida.";
        if ($v['telefono'] && !preg_match('/^[0-9 +().\/-]{6,40}$/', $v['telefono'])) $err[] = 'Telefono non valido.';
        if ($v['codice_fiscale'] && !preg_match('/^([A-Z0-9]{16}|[0-9]{11})$/', $v['codice_fiscale'])) $err[] = 'Il codice fiscale deve avere 16 caratteri (o 11 cifre per le aziende).';
        if ($v['partita_iva'] && !preg_match('/^([0-9]{11}|[A-Z]{2}[0-9A-Z]{2,13})$/', $v['partita_iva'])) $err[] = 'Partita IVA non valida: 11 cifre (o prefisso del paese per le aziende estere).';
        if ($v['codice_sdi'] && !preg_match('/^[A-Z0-9]{6,7}$/', $v['codice_sdi'])) $err[] = 'Il codice destinatario SDI ha 7 caratteri.';
        if ($v['data_nascita'] && eta_da_data($v['data_nascita']) === null) $err[] = 'Data di nascita non valida.';
        if (!isset($statiById[$v['stato_id']])) $err[] = 'Scegli lo stato.';
        if ($v['email'] && !$err) {
            $st = db()->prepare('SELECT id FROM clienti WHERE LOWER(email) = ? AND id <> ?');
            $st->execute([$v['email'], $id]);
            if ($dup = (int)$st->fetchColumn()) $err[] = "Esiste già un cliente con questa email (scheda n. $dup).";
        }
        if ($v['partita_iva'] && !$err) {
            $st = db()->prepare('SELECT id FROM clienti WHERE partita_iva = ? AND id <> ?');
            $st->execute([$v['partita_iva'], $id]);
            if ($dup = (int)$st->fetchColumn()) $err[] = "Esiste già un'azienda con questa partita IVA (scheda n. $dup).";
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
            // "Associa": la persona viene legata all'azienda scelta nel modulo
            // aziende associate: il modulo della persona le elenca tutte, quindi le allineo (stessa tabella vista dalla scheda dell'azienda)
            if ($tipo === 'persona' && isset($_POST['legami_modulo'])) {
                $voglio = [];
                foreach ((array)($_POST['legami'] ?? []) as $r) {
                    $aid = (int)($r['azienda'] ?? 0);
                    if ($aid) $voglio[$aid] = trim(mb_substr((string)($r['ruolo'] ?? ''), 0, 80)) ?: null;
                }
                $st = db()->prepare('DELETE FROM clienti_legami WHERE persona_id = ?' . ($voglio ? ' AND azienda_id NOT IN (' . implode(',', array_keys($voglio)) . ')' : ''));
                $st->execute([$id]);
                foreach ($voglio as $aid => $ruolo) Clienti::lega($id, $aid, $ruolo);
            }
            flash('ok', $tipo === 'azienda' ? 'Azienda salvata.' : 'Cliente salvato.');
            vai('clienti.php?id=' . $id);
        }
        foreach ($err as $m) flash('errore', $m);
        $form = $v + ['id' => $id, 'ragione_sociale' => $t('ragione_sociale', 120)];
    }
    if ($az === 'lega' && $id) {
        $st = db()->prepare('SELECT tipo FROM clienti WHERE id = ?'); $st->execute([$id]); $tipo = (string)$st->fetchColumn();
        $altro = (int)($_POST['altro_id'] ?? 0);
        $ruolo = trim(mb_substr((string)($_POST['ruolo'] ?? ''), 0, 80)) ?: null;
        $ok = $tipo === 'azienda' ? Clienti::lega($altro, $id, $ruolo) : Clienti::lega($id, $altro, $ruolo);
        if ($ok) { Auth::log('associa', 'clienti', $id, 'con ' . $altro); flash('ok', 'Associazione salvata.'); }
        else flash('errore', $tipo === 'azienda' ? 'Scegli una persona dall\'elenco che compare mentre scrivi.' : 'Scegli un\'azienda dall\'elenco.');
        vai('clienti.php?id=' . $id . '#legami');
    }
    if ($az === 'slega' && $id) {
        $altro = (int)($_POST['altro_id'] ?? 0);
        db()->prepare('DELETE FROM clienti_legami WHERE (persona_id = ? AND azienda_id = ?) OR (persona_id = ? AND azienda_id = ?)')->execute([$id, $altro, $altro, $id]);
        Auth::log('dissocia', 'clienti', $id, 'da ' . $altro);
        flash('ok', 'Associazione tolta.');
        vai('clienti.php?id=' . $id . '#legami');
    }
    if ($az === 'sede' && $id) {
        $sid = (int)($_POST['sede_id'] ?? 0);
        $t = fn(string $k, int $max) => trim(mb_substr((string)($_POST[$k] ?? ''), 0, $max)) ?: null;
        if (!empty($_POST['togli']) && $sid) {
            db()->prepare('DELETE FROM clienti_sedi WHERE id = ? AND cliente_id = ?')->execute([$sid, $id]);
            flash('ok', 'Sede eliminata.');
        } else {
            $v = [$t('nome', 120), $t('indirizzo', 190), $t('cap', 10), $t('citta', 100), mb_strtoupper((string)$t('provincia', 60)) ?: null, $t('nazione', 60) ?: 'Italia', $t('telefono', 40), $t('note', 255)];
            if (!$v[0]) flash('errore', 'Dai un nome alla sede (es. Magazzino, Filiale di Torino).');
            elseif ($sid) { db()->prepare('UPDATE clienti_sedi SET nome = ?, indirizzo = ?, cap = ?, citta = ?, provincia = ?, nazione = ?, telefono = ?, note = ? WHERE id = ? AND cliente_id = ?')->execute([...$v, $sid, $id]); flash('ok', 'Sede aggiornata.'); }
            else { db()->prepare('INSERT INTO clienti_sedi (nome, indirizzo, cap, citta, provincia, nazione, telefono, note, cliente_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([...$v, $id]); flash('ok', 'Sede aggiunta.'); }
        }
        Auth::log('modifica', 'clienti', $id, 'sedi');
        vai('clienti.php?id=' . $id . '#sedi');
    }
}

/* ---------- filtri ed export ---------- */
$par = []; $w = ['1=1'];
if (($q = trim((string)($_GET['q'] ?? ''))) !== '') { $w[] = '(c.nome LIKE ? OR c.email LIKE ? OR c.telefono LIKE ? OR c.codice_fiscale LIKE ? OR c.partita_iva LIKE ? OR c.citta LIKE ?)'; array_push($par, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
if (($fs = (int)($_GET['stato'] ?? 0)) > 0) { $w[] = 'c.stato_id = ?'; $par[] = $fs; }
$ft = in_array($_GET['tipo'] ?? '', ['persona', 'azienda'], true) ? $_GET['tipo'] : '';
if ($ft) { $w[] = 'c.tipo = ?'; $par[] = $ft; }
$where = implode(' AND ', $w);
$qs = array_filter(['q' => $q, 'stato' => $fs ?: '', 'tipo' => $ft]);

if (isset($_GET['esporta'])) {
    $st = db()->prepare("SELECT c.id, c.tipo, c.nome, c.nome_proprio, c.cognome, c.email, c.pec, c.telefono, c.codice_fiscale, c.partita_iva, c.codice_sdi, c.data_nascita,
        COALESCE(CONCAT(co.nome, ' (', co.sigla, ')'), c.citta) AS comune, c.provincia, c.cap, c.nazione, c.indirizzo, s.nome AS stato, c.origine, c.creato_il, c.note
        FROM clienti c LEFT JOIN comuni co ON co.id = c.comune_id JOIN stati_cliente s ON s.id = c.stato_id WHERE $where ORDER BY c.nome");
    $st->execute($par);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clienti-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['n', 'tipo', 'nome completo / ragione sociale', 'nome', 'cognome', 'email', 'pec', 'telefono', 'codice_fiscale', 'partita_iva', 'codice_sdi', 'nascita', 'comune / città', 'provincia', 'cap', 'nazione', 'indirizzo', 'stato', 'origine', 'creato', 'note'], ';', '"', '');
    while ($r = $st->fetch()) { $r['creato_il'] = data_it($r['creato_il']); fputcsv($o, $r, ';', '"', ''); }
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
pagina_inizio('Clienti', 'clienti');

/* ---------- modulo nuovo / modifica ---------- */
if (isset($form) || isset($_GET['nuovo']) || ($id && isset($_GET['modifica']))):
    if (!isset($form)) {
        if ($id) { $st = db()->prepare('SELECT * FROM clienti WHERE id = ?'); $st->execute([$id]); $form = $st->fetch() ?: vai('clienti.php'); $form['ragione_sociale'] = $form['nome']; }
        else $form = ['id' => 0, 'tipo' => ($_GET['nuovo'] ?? '') === 'azienda' ? 'azienda' : 'persona', 'stato_id' => Clienti::stato('nuovo'), 'consenso_privacy' => 0, 'nazione' => 'Italia'];
    }
    $comuneTxt = '';
    if (!empty($form['comune_id'])) { $st = db()->prepare('SELECT CONCAT(nome, \' (\', sigla, \')\') FROM comuni WHERE id = ?'); $st->execute([$form['comune_id']]); $comuneTxt = (string)$st->fetchColumn(); }
    $fid = (int)($form['id'] ?? 0);
    $az = $form['tipo'] === 'azienda';
    $aziende = $az ? [] : db()->query("SELECT id, nome FROM clienti WHERE tipo = 'azienda' ORDER BY nome")->fetchAll(PDO::FETCH_KEY_PAIR);
    // aziende già associate (o quelle appena scelte, se il modulo torna con un errore)
    if (isset($_POST['legami_modulo'])) $legamiForm = array_values(array_filter(array_map(fn($r) => ['id' => (int)($r['azienda'] ?? 0), 'ruolo' => (string)($r['ruolo'] ?? '')], (array)($_POST['legami'] ?? [])), fn($r) => $r['id'] > 0));
    else $legamiForm = $fid && !$az ? Clienti::legami($fid, 'persona') : [];
    $f = fn(string $k) => e((string)($form[$k] ?? '')); ?>
  <p class="crumbs"><a href="clienti.php<?= $fid ? '?id=' . $fid : '' ?>">← <?= $fid ? 'Scheda' : 'Clienti' ?></a></p>
  <h1><?= $fid ? ($az ? 'Modifica azienda' : 'Modifica persona') : ($az ? 'Nuova azienda' : 'Nuova persona') ?></h1>
  <form method="post" class="form form--grid" autocomplete="off">
    <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="salva"><input type="hidden" name="id" value="<?= $fid ?>"><input type="hidden" name="tipo" value="<?= $az ? 'azienda' : 'persona' ?>">
    <?php if ($az): ?>
    <label class="full">Ragione sociale *<input name="ragione_sociale" required maxlength="120" value="<?= $f('ragione_sociale') ?>"></label>
    <label>Partita IVA<input name="partita_iva" maxlength="16" value="<?= $f('partita_iva') ?>"></label>
    <label>Codice fiscale<input name="codice_fiscale" maxlength="16" value="<?= $f('codice_fiscale') ?>"><small>Se diverso dalla partita IVA</small></label>
    <label>Codice destinatario SDI<input name="codice_sdi" maxlength="7" value="<?= $f('codice_sdi') ?>"></label>
    <label>PEC<input type="email" name="pec" maxlength="190" value="<?= $f('pec') ?>"></label>
    <label>Email<input type="email" name="email" maxlength="190" value="<?= $f('email') ?>"></label>
    <label>Telefono<input type="tel" name="telefono" maxlength="40" value="<?= $f('telefono') ?>"></label>
    <h2 class="full">Sede legale</h2>
    <label class="full">Indirizzo<input name="indirizzo" maxlength="190" value="<?= $f('indirizzo') ?>"><small class="hint">Via e numero civico</small></label>
    <label>Città<input name="citta" maxlength="100" value="<?= $f('citta') ?>"></label>
    <label>Provincia<input name="provincia" maxlength="60" value="<?= $f('provincia') ?>"><small class="hint">Sigla, es. TO</small></label>
    <label>CAP<input name="cap" maxlength="10" value="<?= $f('cap') ?>" inputmode="numeric"></label>
    <label>Nazione<input name="nazione" maxlength="60" value="<?= $f('nazione') ?>"></label>
    <?php else: ?>
    <label>Nome *<input name="nome_proprio" required maxlength="80" value="<?= $f('nome_proprio') ?>"></label>
    <label>Cognome *<input name="cognome" required maxlength="80" value="<?= $f('cognome') ?>"></label>
    <label>Email<input type="email" name="email" maxlength="190" value="<?= $f('email') ?>"></label>
    <label>Telefono<input type="tel" name="telefono" maxlength="40" value="<?= $f('telefono') ?>"></label>
    <label>Codice fiscale<input name="codice_fiscale" maxlength="16" value="<?= $f('codice_fiscale') ?>"></label>
    <label>Data di nascita<input type="date" name="data_nascita" value="<?= $f('data_nascita') ?>"></label>
    <label>Comune di residenza
      <input name="comune" list="comuni-list" value="<?= e($comuneTxt) ?>" autocomplete="off" data-cerca-comuni>
      <input type="hidden" name="comune_id" value="<?= (int)($form['comune_id'] ?? 0) ?: '' ?>"><datalist id="comuni-list"></datalist><small class="hint">Scrivi almeno 2 lettere e scegli dall'elenco</small>
    </label>
    <label>Indirizzo<input name="indirizzo" maxlength="190" value="<?= $f('indirizzo') ?>"></label>
    <fieldset class="full legami-form"><legend>Aziende associate</legend><input type="hidden" name="legami_modulo" value="1">
    <?php foreach (array_merge($legamiForm, [['id' => 0, 'ruolo' => '']]) as $i => $l): ?>
      <div class="form--grid2">
        <label><?= $l['id'] ? 'Azienda' : ($legamiForm ? 'Associa un\'altra azienda' : 'Associa a un\'azienda') ?><select name="legami[<?= $i ?>][azienda]"><option value=""><?= $l['id'] ? 'Nessuna (togli l\'associazione)' : 'Nessuna' ?></option>
          <?php foreach ($aziende as $k => $n): ?><option value="<?= (int)$k ?>"<?= (int)$k === (int)$l['id'] ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
          <?php if (!$aziende): ?><small class="hint">Nessuna azienda in anagrafica: <a href="clienti.php?nuovo=azienda">creane una</a>.</small><?php endif; ?></label>
        <label>Ruolo nell'azienda<input name="legami[<?= $i ?>][ruolo]" maxlength="80" value="<?= e((string)$l['ruolo']) ?>"><small class="hint">Es. legale rappresentante, dipendente</small></label>
      </div>
    <?php endforeach; ?>
    <small class="hint">Le associazioni valgono in entrambe le schede: le vedi anche nell'anagrafica dell'azienda.</small>
    </fieldset>
    <?php endif; ?>
    <label>Stato<select name="stato_id"><?php foreach ($stati as $s): if (!$s['attivo'] && (int)$s['id'] !== (int)$form['stato_id']) continue; ?><option value="<?= (int)$s['id'] ?>"<?= (int)$s['id'] === (int)$form['stato_id'] ? ' selected' : '' ?>><?= e($s['nome']) ?></option><?php endforeach; ?></select></label>
    <label class="full">Note interne<textarea name="note" rows="4"><?= $f('note') ?></textarea></label>
    <div class="field field--check"><label><input type="checkbox" name="consenso_privacy" value="1"<?= !empty($form['consenso_privacy']) ? ' checked' : '' ?>> Consenso al trattamento dei dati</label></div>
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
    <h1><?= e($c['nome']) ?> <span class="tag"><?= e(Clienti::TIPI[$c['tipo']]) ?></span></h1>
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
      <h2><?= $c['tipo'] === 'azienda' ? 'Dati di fatturazione' : 'Anagrafica' ?></h2>
      <dl class="dl">
      <?php if ($c['tipo'] === 'azienda'): ?>
        <dt>Ragione sociale</dt><dd><?= e($c['nome']) ?></dd>
        <dt>Partita IVA</dt><dd><?= e($c['partita_iva'] ?: '–') ?></dd>
        <dt>Cod. fiscale</dt><dd><?= e($c['codice_fiscale'] ?: '–') ?></dd>
        <dt>Codice SDI</dt><dd><?= e($c['codice_sdi'] ?: '–') ?></dd>
        <dt>PEC</dt><dd><?= $c['pec'] ? '<a href="mailto:' . e($c['pec']) . '">' . e($c['pec']) . '</a>' : '–' ?></dd>
        <dt>Sede legale</dt><dd><?= e(trim(implode(', ', array_filter([$c['indirizzo'], trim(($c['cap'] ?? '') . ' ' . ($c['citta'] ?? '') . ($c['provincia'] ? ' (' . $c['provincia'] . ')' : '')), $c['nazione'] && $c['nazione'] !== 'Italia' ? $c['nazione'] : null]))) ?: '–') ?></dd>
      <?php else: ?>
        <dt>Nome</dt><dd><?= e($c['nome_proprio'] ?: '–') ?></dd>
        <dt>Cognome</dt><dd><?= e($c['cognome'] ?: '–') ?></dd>
        <dt>Cod. fiscale</dt><dd><?= e($c['codice_fiscale'] ?: '–') ?></dd>
        <dt>Nascita</dt><dd><?= $c['data_nascita'] ? e(date('d/m/Y', strtotime($c['data_nascita']))) . ' (' . eta_da_data($c['data_nascita']) . ' anni)' : '–' ?></dd>
        <dt>Comune</dt><dd><?= e($c['comune'] ?: '–') ?></dd>
        <dt>Indirizzo</dt><dd><?= e($c['indirizzo'] ?: '–') ?></dd>
      <?php endif; ?>
        <dt>Email</dt><dd><?= $c['email'] ? '<a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '–' ?></dd>
        <dt>Telefono</dt><dd><?= $c['telefono'] ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $c['telefono'])) . '">' . e($c['telefono']) . '</a>' : '–' ?></dd>
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
  <?php
    $legami = Clienti::legami($id, $c['tipo']);
    $aree = Clienti::polizzePerArea($id);
    $tutte = array_merge(...array_map(fn($a) => $a['polizze'], array_values($aree)));
    $st = db()->prepare('SELECT * FROM documenti WHERE cliente_id = ? ORDER BY id DESC');
    $st->execute([$id]);
    $docs = $st->fetchAll(); ?>
  <div class="cols">
  <section class="box" id="legami">
    <h2><?= $c['tipo'] === 'azienda' ? 'Persone associate' : 'Aziende associate' ?> <span class="muted">(<?= count($legami) ?>)</span></h2>
    <?php if (!$legami): ?><p class="muted"><?= $c['tipo'] === 'azienda' ? 'Nessuna persona associata.' : 'Non è associata a nessuna azienda.' ?></p><?php else: ?>
    <table class="table table--small"><tbody>
    <?php foreach ($legami as $l): ?>
      <tr><td><a href="clienti.php?id=<?= (int)$l['id'] ?>"><b><?= e($l['nome']) ?></b></a><?= $l['ruolo'] ? ' <span class="muted">· ' . e($l['ruolo']) . '</span>' : '' ?><br><small class="muted"><?= e(implode(' · ', array_filter([$l['email'], $l['telefono']]))) ?></small></td>
      <td class="right"><form method="post" class="inline" data-conferma="Togliere l'associazione con <?= e($l['nome']) ?>? Le due schede restano."><?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="slega"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="altro_id" value="<?= (int)$l['id'] ?>"><button class="linkbtn">togli</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table><?php endif; ?>
    <form method="post" class="form form--inline" autocomplete="off">
      <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="lega"><input type="hidden" name="id" value="<?= $id ?>">
      <?php if ($c['tipo'] === 'azienda'): ?>
        <label>Associa una persona<input list="lista-persone" data-cerca-clienti="persona" data-campo="altro_id"><datalist id="lista-persone"></datalist><input type="hidden" name="altro_id"><small class="hint">Scrivi nome, email o codice fiscale e scegli dall'elenco</small></label>
      <?php else:
        $aziende = db()->query("SELECT id, nome FROM clienti WHERE tipo = 'azienda' ORDER BY nome")->fetchAll(PDO::FETCH_KEY_PAIR);
        $gia = array_column($legami, 'id'); ?>
        <label>Associa a un'azienda<select name="altro_id" required><option value="">Scegli…</option><?php foreach ($aziende as $k => $n): if (in_array($k, $gia)) continue; ?><option value="<?= (int)$k ?>"><?= e($n) ?></option><?php endforeach; ?></select></label>
      <?php endif; ?>
      <label>Ruolo<input name="ruolo" maxlength="80"><small class="hint">Es. legale rappresentante</small></label>
      <button class="btn btn--ghost">Associa</button>
    </form>
    <p class="muted"><?= $c['tipo'] === 'azienda' ? '<a href="clienti.php?nuovo=persona">+ Nuova persona</a> (poi associala da qui o dal suo modulo)' : '<a href="clienti.php?nuovo=azienda">+ Nuova azienda</a>' ?></p>
  </section>
  <?php if ($c['tipo'] === 'azienda'):
    $st = db()->prepare('SELECT * FROM clienti_sedi WHERE cliente_id = ? ORDER BY nome'); $st->execute([$id]); $sedi = $st->fetchAll(); ?>
  <section class="box" id="sedi">
    <h2>Sedi aggiuntive <span class="muted">(<?= count($sedi) ?>)</span></h2>
    <?php foreach (array_merge($sedi, [['id' => 0, 'nome' => '', 'indirizzo' => '', 'cap' => '', 'citta' => '', 'provincia' => '', 'nazione' => 'Italia', 'telefono' => '', 'note' => '']]) as $sd): ?>
      <details class="sede"<?= $sd['id'] ? '' : '' ?>>
        <summary><?= $sd['id'] ? '<b>' . e($sd['nome']) . '</b> <span class="muted">' . e(implode(', ', array_filter([$sd['indirizzo'], trim($sd['cap'] . ' ' . $sd['citta'] . ($sd['provincia'] ? ' (' . $sd['provincia'] . ')' : ''))]))) . '</span>' : '+ Aggiungi una sede' ?></summary>
        <form method="post" class="form form--grid2">
          <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="sede"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="sede_id" value="<?= (int)$sd['id'] ?>">
          <label>Nome della sede *<input name="nome" maxlength="120" required value="<?= e($sd['nome']) ?>"><small class="hint">Es. magazzino, filiale di Torino</small></label>
          <label>Telefono<input name="telefono" maxlength="40" value="<?= e((string)$sd['telefono']) ?>"></label>
          <label>Indirizzo<input name="indirizzo" maxlength="190" value="<?= e((string)$sd['indirizzo']) ?>"></label>
          <label>Città<input name="citta" maxlength="100" value="<?= e((string)$sd['citta']) ?>"></label>
          <label>Provincia<input name="provincia" maxlength="60" value="<?= e((string)$sd['provincia']) ?>"></label>
          <label>CAP<input name="cap" maxlength="10" value="<?= e((string)$sd['cap']) ?>"></label>
          <label>Nazione<input name="nazione" maxlength="60" value="<?= e((string)$sd['nazione']) ?>"></label>
          <label>Note<input name="note" maxlength="255" value="<?= e((string)$sd['note']) ?>"></label>
          <div class="actions"><button class="btn btn--ghost"><?= $sd['id'] ? 'Salva la sede' : 'Aggiungi la sede' ?></button>
          <?php if ($sd['id']): ?><button class="btn btn--danger" name="togli" value="1" data-conferma-btn="Eliminare la sede <?= e($sd['nome']) ?>?">Elimina</button><?php endif; ?></div>
        </form>
      </details>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
  </div>
  <section class="box" id="polizze">
    <?php $cnt = array_count_values(array_column($tutte, 'situazione')); ?>
    <div class="head"><h2>Polizze <span class="muted">(<?= (int)($cnt['attiva'] ?? 0) + (int)($cnt['in_scadenza'] ?? 0) ?> in corso<?= !empty($cnt['in_scadenza']) ? ', ' . (int)$cnt['in_scadenza'] . ' in scadenza' : '' ?><?= !empty($cnt['scaduta']) ? ', ' . (int)$cnt['scaduta'] . ' scadute' : '' ?>, <?= count($tutte) ?> in tutto)</span></h2>
      <div class="head__actions"><a class="btn" href="polizze.php?nuovo=1&amp;cliente=<?= $id ?>">+ Nuova polizza</a></div></div>
    <?php if (!$tutte): ?><p class="muted">Nessuna polizza registrata. Aggiungila a mano oppure carica il contratto qui sotto spuntando "È un contratto": compilo io le date.</p><?php else: ?>
    <?php foreach ($aree as $aid => $a): if (!$a['polizze'] && $aid === 0) continue; ?>
      <h3 class="area"><?= e($a['nome']) ?> <span class="muted">(<?= count($a['polizze']) ?>)</span></h3>
      <?php if (!$a['polizze']): ?><p class="muted">Nessuna polizza.</p><?php continue; endif; ?>
      <div class="tablewrap"><table class="table">
      <thead><tr><th>Situazione</th><th>Compagnia e prodotto</th><th>Numero / oggetto</th><th>Frazionamento</th><th>Scadenza annualità</th><th>Prossima scadenza</th><th class="right">Premio annuo</th></tr></thead><tbody>
      <?php foreach ($a['polizze'] as $x): $pr = $x['prossima']; ?>
        <tr<?= in_array($x['situazione'], ['chiusa'], true) ? ' class="off"' : '' ?>>
        <td><span class="tag tag--sit-<?= e($x['situazione']) ?>"><?= e(Clienti::SITUAZIONI[$x['situazione']]) ?></span><?= $x['situazione'] === 'chiusa' ? '<br><small class="muted">' . e(Scadenze::STATI[$x['stato']] ?? $x['stato']) . '</small>' : '' ?></td>
        <td><a href="polizze.php?id=<?= (int)$x['id'] ?>"><b><?= e($x['compagnia']) ?></b></a><br><small class="muted"><?= e($x['prodotto'] ?: '–') ?> · <?= e(Categorie::etichetta($x['categoria_id'] ? (int)$x['categoria_id'] : null)) ?></small></td>
        <td><?= e($x['numero'] ?: '–') ?><br><small class="muted"><?= e((string)$x['oggetto']) ?></small></td>
        <td><?= e(Scadenze::NOMI[$x['frazionamento']] ?? $x['frazionamento']) ?></td>
        <td><?= date('d/m/Y', strtotime($x['scadenza'])) ?></td>
        <td><?= $pr ? '<b>' . date('d/m/Y', strtotime($pr['data'])) . '</b><br><small class="muted">' . ($pr['evento'] === 'rata' ? 'rata' : 'annualità') . '</small>' : '–' ?></td>
        <td class="right"><?= euro($x['premio_annuo']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endforeach; endif; ?>
  </section>
  <section class="box" id="documenti">
    <h2>Documenti <span class="muted">(<?= count($docs) ?>)</span></h2>
    <?php docs_tabella($docs); ?>
    <?php docs_form($id); ?>
  </section>
  <form method="post" class="danger" data-conferma="Eliminare il cliente e TUTTI i suoi preventivi, polizze e documenti? L'operazione non si può annullare.">
    <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $id ?>">
    <button class="btn btn--danger">Elimina cliente, preventivi, polizze e documenti</button>
    <small>Usalo per le richieste di cancellazione dei dati (diritto all'oblio).</small>
  </form>
<?php
/* ---------- elenco ---------- */
else:
    $per = 50; $pag = max(1, (int)($_GET['p'] ?? 1));
    $st = db()->prepare("SELECT COUNT(*) FROM clienti c WHERE $where"); $st->execute($par); $tot = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT c.id, c.tipo, c.nome, c.email, c.telefono, c.stato_id, c.origine, c.aggiornato_il, COALESCE(CONCAT(co.nome, ' (', co.sigla, ')'), CONCAT(c.citta, IF(c.provincia IS NULL, '', CONCAT(' (', c.provincia, ')')))) AS comune,
        (SELECT GROUP_CONCAT(a.nome ORDER BY a.nome SEPARATOR ', ') FROM clienti_legami l JOIN clienti a ON a.id = IF(c.tipo = 'persona', l.azienda_id, l.persona_id) WHERE IF(c.tipo = 'persona', l.persona_id, l.azienda_id) = c.id) AS legati,
        (SELECT COUNT(*) FROM preventivi p WHERE p.cliente_id = c.id) AS n_prev,
        (SELECT COUNT(*) FROM polizze po WHERE po.cliente_id = c.id AND po.stato = 'attiva') AS n_pol
        FROM clienti c LEFT JOIN comuni co ON co.id = c.comune_id WHERE $where ORDER BY c.aggiornato_il DESC LIMIT $per OFFSET " . (($pag - 1) * $per));
    $st->execute($par);
    $conta = db()->query('SELECT stato_id, COUNT(*) FROM clienti GROUP BY stato_id')->fetchAll(PDO::FETCH_KEY_PAIR); ?>
  <div class="head">
    <h1>Clienti <span class="muted">(<?= $tot ?>)</span></h1>
    <div class="head__actions"><a class="btn btn--ghost" href="?<?= e(http_build_query($qs + ['esporta' => 1])) ?>">Esporta CSV</a><a class="btn btn--blu" href="?nuovo=azienda">+ Nuova azienda</a><a class="btn" href="?nuovo=persona">+ Nuova persona</a></div>
  </div>
  <p class="intro">Chi chiede un preventivo dal sito entra qui in automatico (stessa email = stesso cliente). Puoi aggiungere clienti a mano e fare i preventivi dal pannello.</p>
  <?php $ct = db()->query('SELECT tipo, COUNT(*) FROM clienti GROUP BY tipo')->fetchAll(PDO::FETCH_KEY_PAIR); ?>
  <nav class="chips" aria-label="Persone o aziende">
    <a href="?<?= e(http_build_query(array_diff_key($qs, ['tipo' => 1]))) ?>"<?= !$ft ? ' aria-current="page"' : '' ?>>Tutti <span><?= array_sum($ct) ?></span></a>
    <a href="?<?= e(http_build_query(['tipo' => 'persona'] + $qs)) ?>"<?= $ft === 'persona' ? ' aria-current="page"' : '' ?>>Persone <span><?= (int)($ct['persona'] ?? 0) ?></span></a>
    <a href="?<?= e(http_build_query(['tipo' => 'azienda'] + $qs)) ?>"<?= $ft === 'azienda' ? ' aria-current="page"' : '' ?>>Aziende <span><?= (int)($ct['azienda'] ?? 0) ?></span></a>
  </nav>
  <nav class="chips" aria-label="Filtra per stato">
    <a href="clienti.php"<?= !$fs ? ' aria-current="page"' : '' ?>>Tutti</a>
    <?php foreach ($stati as $s): ?><a href="?stato=<?= (int)$s['id'] ?>"<?= $fs === (int)$s['id'] ? ' aria-current="page"' : '' ?> style="--c:<?= e($s['colore']) ?>"><?= e($s['nome']) ?> <span><?= (int)($conta[$s['id']] ?? 0) ?></span></a><?php endforeach; ?>
  </nav>
  <form method="get" class="filters">
    <?php if ($fs): ?><input type="hidden" name="stato" value="<?= $fs ?>"><?php endif; ?><?php if ($ft): ?><input type="hidden" name="tipo" value="<?= e($ft) ?>"><?php endif; ?>
    <span class="campo"><input type="search" name="q" value="<?= e($q) ?>" aria-label="Cerca"><small class="hint">Nome, ragione sociale, email, telefono, codice fiscale o P. IVA</small></span>
    <button class="btn btn--ghost">Cerca</button>
  </form>
  <div class="tablewrap"><table class="table">
    <thead><tr><th>Cliente</th><th>Contatti</th><th>Comune / città</th><th>Stato</th><th class="right">Preventivi</th><th class="right">Polizze attive</th><th>Aggiornato</th></tr></thead>
    <tbody>
    <?php foreach ($st as $c): ?>
      <tr><td><a href="?id=<?= (int)$c['id'] ?>"><b><?= e($c['nome']) ?></b></a> <small class="tag"><?= $c['tipo'] === 'azienda' ? 'Azienda' : 'Persona' ?></small><?= $c['origine'] === 'sito' ? ' <small class="muted">dal sito</small>' : '' ?><?= $c['legati'] ? '<br><small class="muted">' . ($c['tipo'] === 'azienda' ? 'Persone: ' : 'Azienda: ') . e($c['legati']) . '</small>' : '' ?></td>
      <td><?= e($c['email'] ?: '') ?><br><small class="muted"><?= e($c['telefono'] ?: '') ?></small></td>
      <td><?= e($c['comune'] ?: '–') ?></td><td><?= $tag($c['stato_id']) ?></td><td class="right"><?= (int)$c['n_prev'] ?></td><td class="right"><?= (int)$c['n_pol'] ?></td><td><?= e(data_it($c['aggiornato_il'], false)) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$tot): ?><tr><td colspan="7" class="muted">Nessun cliente.</td></tr><?php endif; ?>
    </tbody></table></div>
  <?= paginazione($tot, $pag, $per, $qs) ?>
<?php endif;
pagina_fine();
