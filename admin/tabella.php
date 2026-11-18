<?php
/* Gestione generica di garanzie, fasce d'età, comuni, marche e modelli: elenco, ricerca, modifica, import/export. */
declare(strict_types=1);
require __DIR__ . '/_init.php';
Auth::richiedi();

$t = (string)($_GET['t'] ?? '');
$def = Tabelle::def($t);
if (!$def) vai('index.php');
$campi = $def['campi'];
$calc = $def['calcolati'] ?? [];
$rcId = (int)db()->query('SELECT id FROM garanzie WHERE principale = 1 LIMIT 1')->fetchColumn();

/* ---------- filtri dell'elenco (usati anche da export e modifica massiva) ---------- */
function filtri_sql(array $def, array $src, array &$par): string
{
    $w = ['1=1'];
    $q = trim((string)($src['q'] ?? ''));
    if ($q !== '') {
        $or = [];
        foreach ($def['ricerca'] as $c) { $or[] = "t.$c LIKE ?"; $par[] = '%' . $q . '%'; }
        $w[] = '(' . implode(' OR ', $or) . ')';
    }
    foreach ($def['filtri'] as $f) {
        $v = (string)($src['f_' . $f] ?? '');
        if ($v === '') continue;
        $w[] = "t.$f = ?";
        $par[] = $f === 'attivo' || $f === 'marca_id' ? (int)$v : $v;
    }
    return implode(' AND ', $w);
}
function stato_filtri(array $def, array $src): array
{
    $s = ['t' => (string)($_GET['t'] ?? '')];
    if (trim((string)($src['q'] ?? '')) !== '') $s['q'] = trim((string)$src['q']);
    foreach ($def['filtri'] as $f) if ((string)($src['f_' . $f] ?? '') !== '') $s['f_' . $f] = (string)$src['f_' . $f];
    return $s;
}
function marche_opzioni(): array
{
    return db()->query("SELECT id, CONCAT(nome, IF(tipo_veicolo = 'moto', ' (moto)', '')) AS n FROM marche ORDER BY tipo_veicolo, nome")->fetchAll(PDO::FETCH_KEY_PAIR);
}
function valore_lista(array $c, $v): string
{
    if ($v === null || $v === '') return '<span class="muted">–</span>';
    return match ($c['tipo'] ?? 'testo') {
        'bool' => $v ? '<span class="tag tag--ok">Sì</span>' : '<span class="tag">No</span>',
        'decimale' => e(rtrim(rtrim(number_format((float)$v, 3, ',', '.'), '0'), ',')),
        'scelta' => e($c['opzioni'][$v] ?? (string)$v),
        'testo' => isset($c['pattern']) && str_starts_with((string)$v, '#') && strlen((string)$v) === 7 ? '<span class="tag tag--stato" style="--c:' . e((string)$v) . '">' . e((string)$v) . '</span>' : e((string)$v),
        default => e((string)$v),
    };
}

$sel = 't.*' . implode('', array_map(fn($k, $c) => ", {$c['sql']} AS $k", array_keys($calc), $calc));

/* ---------- azioni ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $az = (string)($_POST['azione'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($az === 'salva') {
        [$v, $err] = Tabelle::leggi($def, $_POST);
        if ($t === 'garanzie') {
            if ($id === $rcId) { $v['attivo'] = 1; $v['tipo_calcolo'] = 'fisso'; $v['applica_coefficienti'] = 1; }
            elseif ($id === 0) { $v['principale'] = 0; $v['dipende_da'] = $rcId; }
            if ($id !== $rcId && in_array($v['tipo_calcolo'], ['perc_rc', 'perc_valore'], true) && ($v['valore'] ?? 0) > 1000) $err['valore'] = 'Valore: una percentuale oltre 1000 non è plausibile.';
        }
        if ($t === 'stati_cliente' && $id) {
            $sys = db()->prepare('SELECT sistema FROM stati_cliente WHERE id = ?'); $sys->execute([$id]);
            if ((int)$sys->fetchColumn() === 1) $v['attivo'] = 1;
        }
        if ($t === 'fasce_eta' && !$err) {
            if ($v['eta_min'] > $v['eta_max']) $err['eta_max'] = 'L\'età finale deve essere uguale o maggiore di quella iniziale.';
            elseif ($v['attivo']) {
                $st = db()->prepare('SELECT CONCAT(eta_min, \'–\', eta_max) FROM fasce_eta WHERE attivo = 1 AND id <> ? AND eta_min <= ? AND eta_max >= ?');
                $st->execute([$id, $v['eta_max'], $v['eta_min']]);
                if ($x = $st->fetchColumn()) $err['eta_min'] = "Si sovrappone alla fascia $x anni.";
            }
        }
        if ($t === 'modelli' && !$err && $v['anno_inizio'] && $v['anno_fine'] && $v['anno_fine'] < $v['anno_inizio']) $err['anno_fine'] = 'L\'anno finale è prima di quello iniziale.';
        if (!$err) {
            try {
                if ($id > 0) {
                    $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($v)));
                    db()->prepare("UPDATE $t SET $set WHERE id = ?")->execute([...array_values($v), $id]);
                    Auth::log('modifica', $t, $id, $v);
                } else {
                    $cols = implode(', ', array_keys($v));
                    db()->prepare("INSERT INTO $t ($cols) VALUES (" . implode(', ', array_fill(0, count($v), '?')) . ')')->execute(array_values($v));
                    $id = (int)db()->lastInsertId();
                    Auth::log('crea', $t, $id, $v);
                }
                flash('ok', ucfirst($def['singolare']) . ' salvato/a.');
                vai('tabella.php?' . http_build_query(stato_filtri($def, $_POST)));
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') $err['_'] = 'Esiste già un elemento con questi dati (codice o nome duplicato).';
                else throw $ex;
            }
        }
        $errori = $err; $valori = $v + ['id' => $id];
    }

    if ($az === 'elimina' && $id > 0) {
        $sys = $t === 'stati_cliente' ? db()->prepare('SELECT sistema FROM stati_cliente WHERE id = ?') : null;
        if ($sys) $sys->execute([$id]);
        if ($t === 'garanzie' && $id === $rcId) flash('errore', 'L\'RC è la garanzia principale e non si può eliminare.');
        elseif ($sys && (int)$sys->fetchColumn() === 1) flash('errore', 'È uno stato automatico: puoi rinominarlo ma non eliminarlo.');
        else {
            try {
                db()->prepare("DELETE FROM $t WHERE id = ?")->execute([$id]);
                Auth::log('elimina', $t, $id);
                flash('ok', ucfirst($def['singolare']) . ' eliminato/a.');
            } catch (PDOException $ex) {
                if ($ex->getCode() !== '23000') throw $ex;
                flash('errore', 'Non si può eliminare: altri dati dipendono da questo elemento (per esempio i modelli di una marca). Puoi disattivarlo.');
            }
        }
        vai('tabella.php?' . http_build_query(stato_filtri($def, $_POST)));
    }

    if ($az === 'massivo' && !empty($def['massivo'])) {
        $par = [];
        $where = filtri_sql($def, $_POST, $par);
        $set = []; $sp = [];
        $coeff = num((string)($_POST['m_coefficiente'] ?? ''));
        if ($coeff !== null) {
            if ($coeff <= 0) { flash('errore', 'Il coefficiente deve essere maggiore di zero.'); vai('tabella.php?' . http_build_query(stato_filtri($def, $_POST))); }
            $set[] = 'coefficiente = ?'; $sp[] = $coeff;
        }
        if (in_array((string)($_POST['m_attivo'] ?? ''), ['0', '1'], true)) { $set[] = 'attivo = ?'; $sp[] = (int)$_POST['m_attivo']; }
        if ($set) {
            $st = db()->prepare("UPDATE $t t SET " . implode(', ', $set) . " WHERE $where");
            $st->execute([...$sp, ...$par]);
            Auth::log('massivo', $t, null, ['filtri' => stato_filtri($def, $_POST), 'coefficiente' => $coeff, 'attivo' => $_POST['m_attivo'] ?? '']);
            flash('ok', 'Aggiornati ' . $st->rowCount() . ' elementi.');
        } else flash('info', 'Nessuna modifica indicata.');
        vai('tabella.php?' . http_build_query(stato_filtri($def, $_POST)));
    }

    if ($az === 'importa' && !empty($def['csv'])) {
        $f = $_FILES['file']['tmp_name'] ?? '';
        if (!$f || !is_uploaded_file($f) || ($_FILES['file']['size'] ?? 0) > 5_000_000) { flash('errore', 'Scegli un file CSV (massimo 5 MB).'); vai("tabella.php?t=$t&importa=1"); }
        $h = fopen($f, 'r');
        $prima = (string)fgets($h);
        $sep = substr_count($prima, ';') >= substr_count($prima, ',') ? ';' : ',';
        $intest = array_map(fn($x) => mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $x))), str_getcsv(trim($prima), $sep, '"', ''));
        $n = 0; $saltate = 0;
        db()->beginTransaction();
        while (($r = fgetcsv($h, null, $sep, '"', '')) !== false) {
            if (count($r) < 2) continue;
            $row = [];
            foreach ($intest as $i => $k) $row[$k] = trim((string)($r[$i] ?? ''));
            $att = isset($row['attivo']) && $row['attivo'] !== '' ? (int)in_array(mb_strtolower($row['attivo']), ['1', 'si', 'sì', 'true', 'x'], true) : null;
            $co = isset($row['coefficiente']) ? num($row['coefficiente']) : null;
            if ($co !== null && $co <= 0) { $saltate++; continue; }
            if ($t === 'comuni') {
                if (empty($row['codice_istat'])) { $saltate++; continue; }
                $st = db()->prepare('UPDATE comuni SET coefficiente = COALESCE(?, coefficiente), attivo = COALESCE(?, attivo) WHERE codice_istat = ?');
                $st->execute([$co, $att, str_pad($row['codice_istat'], 6, '0', STR_PAD_LEFT)]);
                $st->rowCount() ? $n++ : $saltate++;
            } elseif ($t === 'marche') {
                if (empty($row['nome'])) { $saltate++; continue; }
                $tipo = ($row['tipo_veicolo'] ?? $row['tipo'] ?? 'auto') === 'moto' ? 'moto' : 'auto';
                db()->prepare('INSERT INTO marche (nome, tipo_veicolo, coefficiente, attivo) VALUES (?, ?, COALESCE(?, 1), COALESCE(?, 1))
                    ON DUPLICATE KEY UPDATE coefficiente = COALESCE(VALUES(coefficiente), coefficiente), attivo = COALESCE(?, attivo)')
                    ->execute([$row['nome'], $tipo, $co, $att, $att]);
                $n++;
            } elseif ($t === 'modelli') {
                $marca = $row['marca'] ?? $row['marca_nome'] ?? '';
                if ($marca === '' || empty($row['nome'] ?? $row['modello'] ?? '')) { $saltate++; continue; }
                $tipo = ($row['tipo_veicolo'] ?? $row['tipo'] ?? 'auto') === 'moto' ? 'moto' : 'auto';
                $marca = preg_replace('/ \(moto\)$/', '', $marca);
                db()->prepare('INSERT INTO marche (nome, tipo_veicolo) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)')->execute([$marca, $tipo]);
                $mid = (int)db()->lastInsertId();
                $val = isset($row['valore_indicativo']) ? num($row['valore_indicativo']) : null;
                $a1 = ($row['anno_inizio'] ?? '') !== '' ? (int)$row['anno_inizio'] : null;
                $a2 = ($row['anno_fine'] ?? '') !== '' ? (int)$row['anno_fine'] : null;
                db()->prepare('INSERT INTO modelli (marca_id, nome, anno_inizio, anno_fine, coefficiente, valore_indicativo, attivo) VALUES (?, ?, ?, ?, COALESCE(?, 1), ?, COALESCE(?, 1))
                    ON DUPLICATE KEY UPDATE anno_inizio = COALESCE(VALUES(anno_inizio), anno_inizio), anno_fine = COALESCE(VALUES(anno_fine), anno_fine),
                    coefficiente = COALESCE(?, coefficiente), valore_indicativo = COALESCE(VALUES(valore_indicativo), valore_indicativo), attivo = COALESCE(?, attivo)')
                    ->execute([$mid, $row['nome'] ?? $row['modello'], $a1, $a2, $co, $val, $att, $co, $att]);
                $n++;
            }
        }
        db()->commit();
        fclose($h);
        Auth::log('importa', $t, null, "$n righe, $saltate saltate");
        flash('ok', "Import completato: $n righe aggiornate o aggiunte" . ($saltate ? ", $saltate saltate (dati mancanti o non trovati)" : '') . '.');
        vai("tabella.php?t=$t");
    }
}

/* ---------- export CSV ---------- */
if (isset($_GET['esporta']) && !empty($def['csv'])) {
    $par = [];
    $where = filtri_sql($def, $_GET, $par);
    $st = db()->prepare("SELECT $sel FROM $t t WHERE $where ORDER BY {$def['ordine']}");
    $st->execute($par);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $t . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $cols = array_merge(array_keys($campi), $t === 'modelli' ? ['marca'] : []);
    fputcsv($out, $cols, ';', '"', '');
    while ($r = $st->fetch()) {
        if ($t === 'modelli') $r['marca'] = preg_replace('/ \(moto\)$/', '', (string)$r['marca_nome']);
        fputcsv($out, array_map(fn($c) => is_numeric($r[$c] ?? null) && str_contains((string)$r[$c], '.') ? str_replace('.', ',', (string)$r[$c]) : $r[$c], $cols), ';', '"', '');
    }
    exit;
}

/* ---------- pagina ---------- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$nuovo = isset($_GET['nuovo']);
$importa = isset($_GET['importa']);
$filtri = stato_filtri($def, $_GET);
pagina_inizio($def['titolo'], $t);

if ($importa && !empty($def['csv'])):
    $esempio = ['comuni' => "codice_istat;coefficiente;attivo\n001120;1,15;1", 'marche' => "nome;tipo_veicolo;coefficiente;attivo\nFiat;auto;1;1",
        'modelli' => "marca;tipo_veicolo;nome;anno_inizio;anno_fine;coefficiente;valore_indicativo;attivo\nFiat;auto;Panda;2012;;1,05;14000;1"][$t]; ?>
  <h1>Importa <?= e(mb_strtolower($def['titolo'])) ?> da CSV</h1>
  <p>File CSV con separatore <code>;</code> o <code>,</code> e la prima riga di intestazione. Le colonne mancanti non vengono toccate. Il modo più semplice: <a href="?<?= e(http_build_query(['t' => $t, 'esporta' => 1])) ?>">esporta il CSV</a>, modificalo in Excel e reimportalo.</p>
  <?php if ($t === 'comuni'): ?><p>Per i comuni si aggiornano coefficiente e stato, cercando il comune per codice ISTAT.</p><?php else: ?><p>Gli elementi nuovi vengono aggiunti, quelli già presenti (stesso nome) aggiornati.</p><?php endif; ?>
  <pre class="code"><?= e($esempio) ?></pre>
  <form method="post" enctype="multipart/form-data" class="form form--narrow">
    <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="importa">
    <label>File CSV<input type="file" name="file" accept=".csv,text/csv" required></label>
    <div class="actions"><button class="btn">Importa</button><a class="btn btn--ghost" href="tabella.php?t=<?= e($t) ?>">Annulla</a></div>
  </form>
<?php
elseif ($id !== null || $nuovo || isset($errori)):
    if (!isset($valori)) {
        $valori = [];
        if ($id) {
            $st = db()->prepare("SELECT * FROM $t WHERE id = ?");
            $st->execute([$id]);
            $valori = $st->fetch() ?: vai("tabella.php?t=$t");
        } else foreach ($campi as $k => $c) $valori[$k] = $c['predefinito'] ?? null;
    }
    $id = (int)($valori['id'] ?? 0);
    $isRc = $t === 'garanzie' && $id === $rcId; ?>
  <p class="crumbs"><a href="tabella.php?<?= e(http_build_query($filtri)) ?>">← <?= e($def['titolo']) ?></a></p>
  <h1><?= $id ? 'Modifica ' . e($def['singolare']) : 'Nuovo/a ' . e($def['singolare']) ?></h1>
  <?php if ($isRc): ?><p class="flash flash--info">Questa è la garanzia principale: il valore è il <strong>premio base dell'RC</strong> in euro, a cui si applicano i coefficienti di comune, marca e modello e il supplemento per l'età.</p>
  <?php elseif ($t === 'garanzie'): ?><p class="flash flash--info">Questa garanzia è legata all'RC: nel preventivatore si può aggiungere solo insieme alla responsabilità civile.</p><?php endif; ?>
  <?php if (!empty($errori['_'])): ?><p class="flash flash--errore"><?= e($errori['_']) ?></p><?php endif; ?>
  <form method="post" class="form form--grid" novalidate>
    <?= Auth::campoCsrf() ?>
    <input type="hidden" name="azione" value="salva"><input type="hidden" name="id" value="<?= $id ?>">
    <?php foreach ($filtri as $k => $v) if ($k !== 't') echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">'; ?>
    <?php foreach ($campi as $k => $c):
        if ($isRc && in_array($k, ['tipo_calcolo', 'applica_coefficienti', 'attivo'], true)) continue;
        if (!empty($c['sola_lettura'])) continue;
        $v = $valori[$k] ?? null; $er = $errori[$k] ?? null;
        $aid = 'f_' . $k; ?>
      <div class="field<?= $c['tipo'] === 'bool' ? ' field--check' : '' ?><?= $er ? ' field--err' : '' ?>">
      <?php if ($c['tipo'] === 'bool'): ?>
        <label><input type="checkbox" name="<?= e($k) ?>" value="1"<?= $v ? ' checked' : '' ?>> <?= e($c['label']) ?></label>
      <?php else: ?>
        <label for="<?= $aid ?>"><?= e($c['label']) ?><?= !empty($c['obbl']) ? ' *' : '' ?></label>
        <?php if ($c['tipo'] === 'scelta'): ?>
          <select id="<?= $aid ?>" name="<?= e($k) ?>"><?php foreach ($c['opzioni'] as $ov => $ol): ?><option value="<?= e($ov) ?>"<?= (string)$v === (string)$ov ? ' selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
        <?php elseif ($c['tipo'] === 'marca'): ?>
          <select id="<?= $aid ?>" name="<?= e($k) ?>"><option value="">Scegli…</option><?php foreach (marche_opzioni() as $ov => $ol): ?><option value="<?= (int)$ov ?>"<?= (int)$v === (int)$ov ? ' selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
        <?php else:
            $vv = $c['tipo'] === 'decimale' && $v !== null && $v !== '' ? rtrim(rtrim(number_format((float)$v, 3, ',', ''), '0'), ',') : (string)$v; ?>
          <input id="<?= $aid ?>" name="<?= e($k) ?>" value="<?= e($vv) ?>"<?= $c['tipo'] === 'intero' || $c['tipo'] === 'decimale' ? ' inputmode="decimal"' : '' ?><?= isset($c['max']) ? ' maxlength="' . (int)$c['max'] . '"' : '' ?>>
        <?php endif; ?>
      <?php endif; ?>
        <?php if (!empty($c['aiuto'])): ?><small><?= e($c['aiuto']) ?></small><?php endif; ?>
        <?php if ($er): ?><small class="err"><?= e($er) ?></small><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="actions"><button class="btn">Salva</button><a class="btn btn--ghost" href="tabella.php?<?= e(http_build_query($filtri)) ?>">Annulla</a></div>
  </form>
  <?php if ($id && !$isRc): ?>
  <form method="post" class="danger" data-conferma="Eliminare definitivamente questo elemento?">
    <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= $id ?>">
    <?php foreach ($filtri as $k => $v) if ($k !== 't') echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">'; ?>
    <button class="btn btn--danger">Elimina</button>
  </form>
  <?php endif;
else:
    $par = [];
    $where = filtri_sql($def, $_GET, $par);
    $per = 50;
    $pag = max(1, (int)($_GET['p'] ?? 1));
    $st = db()->prepare("SELECT COUNT(*) FROM $t t WHERE $where");
    $st->execute($par);
    $tot = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT $sel FROM $t t WHERE $where ORDER BY {$def['ordine']} LIMIT $per OFFSET " . (($pag - 1) * $per));
    $st->execute($par);
    $righe = $st->fetchAll();
    $lab = fn($k) => $campi[$k]['breve'] ?? $campi[$k]['label'] ?? $calc[$k]['label'] ?? $k; ?>
  <div class="head">
    <h1><?= e($def['titolo']) ?> <span class="muted">(<?= number_format($tot, 0, ',', '.') ?>)</span></h1>
    <div class="head__actions">
      <?php if (!empty($def['csv'])): ?><a class="btn btn--ghost" href="?<?= e(http_build_query($filtri + ['esporta' => 1])) ?>">Esporta CSV</a><a class="btn btn--ghost" href="?t=<?= e($t) ?>&amp;importa=1">Importa CSV</a><?php endif; ?>
      <a class="btn" href="?<?= e(http_build_query($filtri + ['nuovo' => 1])) ?>">+ Aggiungi</a>
    </div>
  </div>
  <?php if (!empty($def['intro'])): ?><p class="intro"><?= e($def['intro']) ?></p><?php endif; ?>
  <form method="get" class="filters">
    <input type="hidden" name="t" value="<?= e($t) ?>">
    <input type="search" name="q" placeholder="Cerca…" value="<?= e($filtri['q'] ?? '') ?>" aria-label="Cerca">
    <?php foreach ($def['filtri'] as $f):
        $cur = $filtri['f_' . $f] ?? '';
        if ($f === 'attivo'): ?>
          <select name="f_attivo" aria-label="Stato"><option value="">Tutti</option><option value="1"<?= $cur === '1' ? ' selected' : '' ?>>Solo attivi</option><option value="0"<?= $cur === '0' ? ' selected' : '' ?>>Solo non attivi</option></select>
        <?php elseif ($f === 'marca_id'): ?>
          <select name="f_marca_id" aria-label="Marca"><option value="">Tutte le marche</option><?php foreach (marche_opzioni() as $ov => $ol): ?><option value="<?= (int)$ov ?>"<?= $cur === (string)$ov ? ' selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
        <?php elseif (($campi[$f]['tipo'] ?? '') === 'scelta'): ?>
          <select name="f_<?= e($f) ?>" aria-label="<?= e($campi[$f]['label']) ?>"><option value=""><?= e($campi[$f]['label']) ?>: tutti</option><?php foreach ($campi[$f]['opzioni'] as $ov => $ol): ?><option value="<?= e($ov) ?>"<?= $cur === $ov ? ' selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
        <?php else:
            $vals = db()->query("SELECT DISTINCT $f FROM $t ORDER BY $f")->fetchAll(PDO::FETCH_COLUMN); ?>
          <select name="f_<?= e($f) ?>" aria-label="<?= e($campi[$f]['label']) ?>"><option value=""><?= e($campi[$f]['label']) ?>: tutte</option><?php foreach ($vals as $ov): ?><option<?= $cur === (string)$ov ? ' selected' : '' ?>><?= e((string)$ov) ?></option><?php endforeach; ?></select>
        <?php endif;
    endforeach; ?>
    <button class="btn btn--ghost">Filtra</button>
    <?php if (count($filtri) > 1): ?><a href="?t=<?= e($t) ?>">Azzera</a><?php endif; ?>
  </form>
  <div class="tablewrap"><table class="table">
    <thead><tr><?php foreach ($def['lista'] as $k): ?><th scope="col"><?= e($lab($k)) ?></th><?php endforeach; ?><th></th></tr></thead>
    <tbody>
    <?php foreach ($righe as $r): ?>
      <tr<?= isset($r['attivo']) && !$r['attivo'] ? ' class="off"' : '' ?>>
        <?php foreach ($def['lista'] as $i => $k): ?><td><?= $i === 0 || $k === 'nome' && $i === 1 ? '' : '' ?><?= valore_lista($campi[$k] ?? [], $r[$k] ?? null) ?><?= $t === 'garanzie' && $k === 'nome' && $r['principale'] ? ' <span class="tag tag--main">Principale</span>' : '' ?></td><?php endforeach; ?>
        <td class="right"><a href="?<?= e(http_build_query($filtri + ['id' => $r['id']])) ?>">Modifica</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$righe): ?><tr><td colspan="<?= count($def['lista']) + 1 ?>" class="muted">Nessun risultato.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= paginazione($tot, $pag, $per, $filtri) ?>
  <?php if (!empty($def['massivo']) && $tot > 0): ?>
  <details class="bulk">
    <summary>Modifica tutti i <?= number_format($tot, 0, ',', '.') ?> elementi filtrati</summary>
    <form method="post" class="form form--inline" data-conferma="Applicare la modifica a <?= $tot ?> elementi?">
      <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="massivo">
      <?php foreach ($filtri as $k => $v) if ($k !== 't') echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">'; ?>
      <label>Nuovo coefficiente<input name="m_coefficiente" inputmode="decimal" placeholder="es. 1,10"></label>
      <label>Stato<select name="m_attivo"><option value="">Non cambiare</option><option value="1">Attiva tutti</option><option value="0">Disattiva tutti</option></select></label>
      <button class="btn">Applica</button>
    </form>
  </details>
  <?php endif;
endif;
pagina_fine();
