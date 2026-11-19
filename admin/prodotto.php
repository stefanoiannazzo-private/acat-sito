<?php
/* Scheda del prodotto: dati, garanzie con le tariffe, pagamento e durata, promozioni e correzioni del prezzo. */
declare(strict_types=1);
require __DIR__ . '/_init.php';
Auth::richiedi();

const VEICOLI = ['entrambi' => 'Auto e moto', 'auto' => 'Solo auto', 'moto' => 'Solo moto'];
const CORREZIONI = ['eta' => 'Età del contraente', 'provincia' => 'Provincia di residenza', 'regione' => 'Regione di residenza', 'marca' => 'Marca del veicolo', 'veicolo' => 'Tipo di veicolo'];
const CALCOLI = ['fisso' => 'Importo fisso (€)', 'perc_rc' => '% dell\'RC', 'perc_valore' => '% del valore del veicolo'];

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
function prodotto(int $id): ?array
{
    $st = db()->prepare('SELECT p.*, c.nome AS compagnia FROM prodotti p JOIN compagnie c ON c.id = p.compagnia_id WHERE p.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
/** Codice interno della garanzia ricavato dal nome (es. "Atti vandalici" → ATTI_VANDALICI): lega la stessa garanzia fra compagnie diverse */
function codice_da_nome(string $nome, int $prodotto): string
{
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome) ?: $nome;
    $c = substr(trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($t)), '_'), 0, 26) ?: 'GARANZIA';
    $st = db()->prepare('SELECT codice FROM garanzie WHERE prodotto_id = ?'); $st->execute([$prodotto]);
    $usati = array_map('strtoupper', $st->fetchAll(PDO::FETCH_COLUMN));
    $base = $c; $i = 2;
    while (in_array($c, $usati, true)) $c = $base . '_' . $i++;
    return $c;
}
function dec(mixed $v): ?float { $v = trim((string)$v); return $v === '' ? null : num($v); }
function data_o_null(string $v): ?string { $v = trim($v); return $v !== '' && DateTimeImmutable::createFromFormat('!Y-m-d', $v) ? $v : null; }
function nd(?string $v, int $dec = 2): string { return $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float)$v, $dec, ',', ''), '0'), ','); }

$p = $id ? (prodotto($id) ?? vai('tabella.php?t=prodotti')) : null;

/* ---------- azioni ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $az = (string)($_POST['azione'] ?? '');
    $torna = function (string $anchor = '') use (&$id): never { vai('prodotto.php?id=' . $id . $anchor); };

    if ($az === 'dati') {
        $v = [
            'compagnia_id' => (int)($_POST['compagnia_id'] ?? 0), 'nome' => trim(mb_substr((string)($_POST['nome'] ?? ''), 0, 120)),
            'categoria_id' => (int)($_POST['categoria_id'] ?? 0) ?: null, 'descrizione' => trim(mb_substr((string)($_POST['descrizione'] ?? ''), 0, 255)) ?: null,
            'attivo' => empty($_POST['attivo']) ? 0 : 1, 'preventivabile' => empty($_POST['preventivabile']) ? 0 : 1,
            'veicoli' => isset(VEICOLI[$_POST['veicoli'] ?? '']) ? $_POST['veicoli'] : 'entrambi',
            'premio_minimo' => dec($_POST['premio_minimo'] ?? ''), 'premio_massimo' => dec($_POST['premio_massimo'] ?? ''),
        ];
        $err = [];
        if (!$v['compagnia_id']) $err[] = 'Scegli la compagnia.';
        if ($v['nome'] === '') $err[] = 'Scrivi il nome del prodotto.';
        if (!$v['categoria_id']) $err[] = 'Scegli la classificazione.';
        if ($v['premio_minimo'] !== null && $v['premio_massimo'] !== null && $v['premio_minimo'] > $v['premio_massimo']) $err[] = 'Il premio minimo supera il massimo.';
        if ($v['preventivabile'] && $id) {
            $st = db()->prepare('SELECT COUNT(*) FROM garanzie WHERE prodotto_id = ? AND principale = 1 AND attivo = 1'); $st->execute([$id]);
            if (!$st->fetchColumn()) $err[] = 'Per metterlo nel preventivatore online serve prima la garanzia RC con il premio base.';
        }
        if ($err) { foreach ($err as $m) flash('errore', $m); $id ? $torna() : vai('prodotto.php'); }
        try {
            if ($id) {
                db()->prepare('UPDATE prodotti SET compagnia_id = ?, nome = ?, categoria_id = ?, descrizione = ?, attivo = ?, preventivabile = ?, veicoli = ?, premio_minimo = ?, premio_massimo = ? WHERE id = ?')
                    ->execute([...array_values($v), $id]);
                Auth::log('modifica', 'prodotti', $id);
            } else {
                $v['preventivabile'] = 0;
                db()->prepare('INSERT INTO prodotti (compagnia_id, nome, categoria_id, descrizione, attivo, preventivabile, veicoli, premio_minimo, premio_massimo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute(array_values($v));
                $id = (int)db()->lastInsertId();
                db()->prepare("INSERT INTO prodotti_frazionamenti (prodotto_id, frazionamento, variazione) VALUES (?, 'annuale', 0)")->execute([$id]);
                Auth::log('crea', 'prodotti', $id);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { flash('errore', 'Esiste già un prodotto con questo nome per la stessa compagnia.'); $id ? $torna() : vai('prodotto.php'); }
            throw $e;
        }
        flash('ok', 'Dati del prodotto salvati.');
        $torna();
    }
    if (!$p) vai('tabella.php?t=prodotti');

    if ($az === 'garanzie') {
        $err = [];
        $righe = (array)($_POST['g'] ?? []);
        $st = db()->prepare('SELECT id FROM garanzie WHERE prodotto_id = ? AND principale = 1'); $st->execute([$id]);
        $rcId = (int)$st->fetchColumn();
        db()->beginTransaction();
        foreach ($righe as $gid => $g) {
            $gid = (int)$gid;
            $nuova = $gid <= 0;
            $codice = strtoupper(trim((string)($g['codice'] ?? '')));
            if ($codice === '' && trim((string)($g['nome'] ?? '')) !== '') $codice = codice_da_nome((string)$g['nome'], $id);
            $nome = trim(mb_substr((string)($g['nome'] ?? ''), 0, 80));
            if ($nuova && $codice === '' && $nome === '') continue;
            $isRc = $gid === $rcId || ($nuova && $gid === -1 && !$rcId);
            if (!$isRc && !$nuova && !empty($g['togli'])) { db()->prepare('DELETE FROM garanzie WHERE id = ? AND prodotto_id = ?')->execute([$gid, $id]); continue; }
            if ($isRc) $codice = $codice ?: 'RC';
            if (!preg_match('/^[A-Z0-9_]{1,30}$/', $codice)) { $err[] = "Codice non valido per \"$nome\": usa lettere maiuscole, numeri e _."; continue; }
            if ($nome === '') { $err[] = "Manca il nome della garanzia $codice."; continue; }
            if (!$isRc && !$rcId) { $err[] = "Prima di $codice inserisci l'RC con il premio base."; continue; }
            $tipo = $isRc ? 'fisso' : (isset(CALCOLI[$g['tipo_calcolo'] ?? '']) ? $g['tipo_calcolo'] : 'fisso');
            $valore = num((string)($g['valore'] ?? ''));
            if ($valore === null || $valore < 0) { $err[] = "Valore non valido per $codice."; continue; }
            if ($tipo !== 'fisso' && $valore > 1000) { $err[] = "Percentuale non plausibile per $codice."; continue; }
            $vals = [$codice, $nome, trim(mb_substr((string)($g['descrizione'] ?? ''), 0, 255)) ?: null, $tipo, round($valore, 2),
                $isRc ? 1 : (empty($g['coeff']) ? 0 : 1), max(0, min(9999, (int)($g['ordine'] ?? 100))), $isRc ? 1 : (empty($g['attivo']) ? 0 : 1)];
            try {
                if ($nuova) {
                    db()->prepare('INSERT INTO garanzie (prodotto_id, codice, nome, descrizione, tipo_calcolo, valore, applica_coefficienti, ordine, attivo, principale, dipende_da) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$id, ...$vals, $isRc ? 1 : 0, $isRc ? null : ($rcId ?: null)]);
                    if ($isRc) $rcId = (int)db()->lastInsertId();
                } else {
                    db()->prepare('UPDATE garanzie SET codice = ?, nome = ?, descrizione = ?, tipo_calcolo = ?, valore = ?, applica_coefficienti = ?, ordine = ?, attivo = ? WHERE id = ? AND prodotto_id = ?')
                        ->execute([...$vals, $gid, $id]);
                }
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') throw $e;
                $err[] = "Il codice $codice è già usato da un'altra garanzia del prodotto.";
            }
        }
        // ogni accessoria dipende dall'RC del prodotto
        if ($rcId) db()->prepare('UPDATE garanzie SET dipende_da = ? WHERE prodotto_id = ? AND principale = 0')->execute([$rcId, $id]);
        db()->commit();
        Auth::log('modifica', 'prodotti', $id, 'garanzie');
        foreach ($err as $m) flash('errore', $m);
        if (!$err) flash('ok', 'Garanzie salvate.');
        $torna('#garanzie');
    }

    if ($az === 'pagamento') {
        db()->beginTransaction();
        db()->prepare('DELETE FROM prodotti_frazionamenti WHERE prodotto_id = ?')->execute([$id]);
        $n = 0;
        foreach (Preventivatore::FRAZIONAMENTI as $k => $l) {
            if (empty($_POST['fraz'][$k])) continue;
            $var = num((string)($_POST['var'][$k] ?? '0')) ?? 0;
            db()->prepare('INSERT INTO prodotti_frazionamenti (prodotto_id, frazionamento, variazione) VALUES (?, ?, ?)')->execute([$id, $k, max(-50, min(100, round($var, 2)))]);
            $n++;
        }
        if (!$n) db()->prepare("INSERT INTO prodotti_frazionamenti (prodotto_id, frazionamento, variazione) VALUES (?, 'annuale', 0)")->execute([$id]);
        db()->prepare('DELETE FROM prodotti_durate WHERE prodotto_id = ?')->execute([$id]);
        for ($a = 1; $a <= 5; $a++) {
            if ($a > 1 && empty($_POST['dur'][$a])) continue;
            $sc = $a === 1 ? 0 : (num((string)($_POST['sconto'][$a] ?? '0')) ?? 0);
            db()->prepare('INSERT INTO prodotti_durate (prodotto_id, anni, sconto) VALUES (?, ?, ?)')->execute([$id, $a, max(0, min(90, round($sc, 2)))]);
        }
        db()->commit();
        Auth::log('modifica', 'prodotti', $id, 'pagamento e durata');
        flash('ok', $n ? 'Pagamento e durata salvati.' : 'Serve almeno un frazionamento: ho lasciato l\'annuale.');
        $torna('#pagamento');
    }

    if ($az === 'promozioni') {
        $err = [];
        foreach ((array)($_POST['pr'] ?? []) as $pid => $r) {
            $pid = (int)$pid;
            $nome = trim(mb_substr((string)($r['nome'] ?? ''), 0, 120));
            if ($pid > 0 && !empty($r['togli'])) { db()->prepare('DELETE FROM promozioni WHERE id = ? AND prodotto_id = ?')->execute([$pid, $id]); continue; }
            if ($pid <= 0 && $nome === '') continue;
            $tipo = ($r['tipo'] ?? '') === 'importo' ? 'importo' : 'percentuale';
            $val = num((string)($r['valore'] ?? ''));
            if ($nome === '' || $val === null || $val <= 0 || ($tipo === 'percentuale' && $val > 90)) { $err[] = 'Promozione "' . ($nome ?: 'senza nome') . '": controlla nome e valore.'; continue; }
            $dal = data_o_null((string)($r['dal'] ?? '')); $al = data_o_null((string)($r['al'] ?? ''));
            if ($dal && $al && $dal > $al) { $err[] = "Promozione \"$nome\": la data di fine è prima dell'inizio."; continue; }
            $v = [$nome, $tipo, round($val, 2), $dal, $al, empty($r['attivo']) ? 0 : 1];
            if ($pid > 0) db()->prepare('UPDATE promozioni SET nome = ?, tipo = ?, valore = ?, dal = ?, al = ?, attivo = ? WHERE id = ? AND prodotto_id = ?')->execute([...$v, $pid, $id]);
            else db()->prepare('INSERT INTO promozioni (nome, tipo, valore, dal, al, attivo, prodotto_id) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([...$v, $id]);
        }
        Auth::log('modifica', 'prodotti', $id, 'promozioni');
        foreach ($err as $m) flash('errore', $m);
        if (!$err) flash('ok', 'Promozioni salvate.');
        $torna('#promozioni');
    }

    if ($az === 'correzioni') {
        $err = [];
        foreach ((array)($_POST['co'] ?? []) as $cid => $r) {
            $cid = (int)$cid;
            if ($cid > 0 && !empty($r['togli'])) { db()->prepare('DELETE FROM prodotti_correzioni WHERE id = ? AND prodotto_id = ?')->execute([$cid, $id]); continue; }
            $tipo = (string)($r['tipo'] ?? '');
            $pct = num((string)($r['variazione'] ?? ''));
            $m = $pct === null ? null : 1 + $pct / 100;
            if ($cid <= 0 && $tipo === '') continue;
            if (!isset(CORREZIONI[$tipo]) || $m === null || $pct <= -90 || $pct > 500 || $pct == 0) { $err[] = 'Variazione di prezzo: scegli quando si applica e la percentuale (es. 10 = +10%, -5 = −5%).'; continue; }
            $min = $max = null; $chiave = null;
            if ($tipo === 'eta') {
                $min = (int)($r['eta_min'] ?? 0); $max = (int)($r['eta_max'] ?? 0);
                if ($min < 14 || $max > 120 || $min > $max) { $err[] = 'Correzione per età: indica un intervallo valido (es. 18–25).'; continue; }
            } elseif ($tipo === 'marca') {
                $chiave = (string)(int)($r['marca'] ?? 0);
                if ($chiave === '0') { $err[] = 'Correzione per marca: scegli la marca.'; continue; }
            } else {
                $chiave = trim(mb_substr((string)($tipo === 'veicolo' ? ($r['veicolo'] ?? '') : ($r['chiave'] ?? '')), 0, 60));
                if ($tipo === 'provincia') $chiave = strtoupper($chiave);
                if ($tipo === 'veicolo') $chiave = strtolower($chiave);
                if ($chiave === '' || ($tipo === 'provincia' && !preg_match('/^[A-Z]{2}$/', $chiave)) || ($tipo === 'veicolo' && !in_array($chiave, ['auto', 'moto'], true))) { $err[] = 'Correzione per ' . CORREZIONI[$tipo] . ': valore non valido.'; continue; }
            }
            $v = [$tipo, $min, $max, $chiave, round($m, 3), trim(mb_substr((string)($r['descrizione'] ?? ''), 0, 120)) ?: null];
            if ($cid > 0) db()->prepare('UPDATE prodotti_correzioni SET tipo = ?, eta_min = ?, eta_max = ?, chiave = ?, moltiplicatore = ?, descrizione = ? WHERE id = ? AND prodotto_id = ?')->execute([...$v, $cid, $id]);
            else db()->prepare('INSERT INTO prodotti_correzioni (tipo, eta_min, eta_max, chiave, moltiplicatore, descrizione, prodotto_id) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([...$v, $id]);
        }
        Auth::log('modifica', 'prodotti', $id, 'correzioni');
        foreach ($err as $m) flash('errore', $m);
        if (!$err) flash('ok', 'Correzioni salvate.');
        $torna('#correzioni');
    }

    if ($az === 'duplica') {
        db()->beginTransaction();
        $nome = mb_substr($p['nome'] . ' (copia)', 0, 120);
        for ($i = 2; ; $i++) {
            $st = db()->prepare('SELECT COUNT(*) FROM prodotti WHERE compagnia_id = ? AND nome = ?'); $st->execute([$p['compagnia_id'], $nome]);
            if (!$st->fetchColumn()) break;
            $nome = mb_substr($p['nome'] . " (copia $i)", 0, 120);
        }
        db()->prepare('INSERT INTO prodotti (compagnia_id, categoria_id, nome, descrizione, preventivabile, veicoli, premio_minimo, premio_massimo, attivo) SELECT compagnia_id, categoria_id, ?, descrizione, 0, veicoli, premio_minimo, premio_massimo, attivo FROM prodotti WHERE id = ?')->execute([$nome, $id]);
        $nid = (int)db()->lastInsertId();
        // prima l'RC, poi le accessorie legate alla nuova RC
        db()->prepare('INSERT INTO garanzie (prodotto_id, codice, nome, descrizione, principale, dipende_da, tipo_calcolo, valore, applica_coefficienti, ordine, attivo) SELECT ?, codice, nome, descrizione, 1, NULL, tipo_calcolo, valore, applica_coefficienti, ordine, attivo FROM garanzie WHERE prodotto_id = ? AND principale = 1')->execute([$nid, $id]);
        $rc = (int)db()->lastInsertId() ?: null;
        db()->prepare('INSERT INTO garanzie (prodotto_id, codice, nome, descrizione, principale, dipende_da, tipo_calcolo, valore, applica_coefficienti, ordine, attivo) SELECT ?, codice, nome, descrizione, 0, ?, tipo_calcolo, valore, applica_coefficienti, ordine, attivo FROM garanzie WHERE prodotto_id = ? AND principale = 0' . ($rc ? '' : ' AND 0'))->execute([$nid, $rc, $id]);
        foreach (['prodotti_frazionamenti' => 'frazionamento, variazione', 'prodotti_durate' => 'anni, sconto', 'promozioni' => 'nome, tipo, valore, dal, al, attivo', 'prodotti_correzioni' => 'tipo, eta_min, eta_max, chiave, moltiplicatore, descrizione'] as $t => $c)
            db()->prepare("INSERT INTO $t (prodotto_id, $c) SELECT ?, $c FROM $t WHERE prodotto_id = ?")->execute([$nid, $id]);
        db()->commit();
        Auth::log('crea', 'prodotti', $nid, 'copia del prodotto ' . $id);
        flash('ok', 'Prodotto duplicato: la copia non è nel preventivatore online finché non lo attivi.');
        vai('prodotto.php?id=' . $nid);
    }

    if ($az === 'elimina') {
        $st = db()->prepare('SELECT COUNT(*) FROM polizze WHERE prodotto_id = ?'); $st->execute([$id]);
        if ($st->fetchColumn()) { flash('errore', 'Ci sono polizze collegate a questo prodotto: disattivalo invece di eliminarlo.'); $torna(); }
        db()->prepare('UPDATE preventivi SET prodotto_id = NULL WHERE prodotto_id = ?')->execute([$id]);
        // prima le garanzie facoltative (dipendono dall'RC), poi l'RC, poi il prodotto con il resto del listino
        db()->beginTransaction();
        db()->prepare('DELETE FROM garanzie WHERE prodotto_id = ? AND principale = 0')->execute([$id]);
        db()->prepare('DELETE FROM garanzie WHERE prodotto_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM prodotti WHERE id = ?')->execute([$id]);
        db()->commit();
        Auth::log('elimina', 'prodotti', $id, $p['nome']);
        flash('ok', 'Prodotto eliminato.');
        vai('tabella.php?t=prodotti');
    }
    $torna();
}

/* ---------- pagina ---------- */
$compagnie = db()->query('SELECT id, CONCAT(nome, IF(attivo = 1, \'\', \' (non attiva)\')) FROM compagnie ORDER BY nome')->fetchAll(PDO::FETCH_KEY_PAIR);
$f = $p ?? ['compagnia_id' => (int)($_GET['compagnia'] ?? 0), 'nome' => '', 'categoria_id' => null, 'descrizione' => '', 'attivo' => 1, 'preventivabile' => 0, 'veicoli' => 'entrambi', 'premio_minimo' => null, 'premio_massimo' => null];
pagina_inizio($p ? $p['nome'] : 'Nuovo prodotto', 'prodotti');
?>
<p class="crumbs"><a href="tabella.php?t=prodotti">← Prodotti</a></p>
<h1><?= $p ? e($p['compagnia'] . ' – ' . $p['nome']) : 'Nuovo prodotto' ?> <?= $p && $p['preventivabile'] ? '<span class="tag tag--ok">nel preventivatore online</span>' : '' ?></h1>
<p class="intro">Un prodotto è un tipo di polizza di una compagnia (es. Allianz – Bonus Malus auto). Qui imposti, nell'ordine: <b>1.</b> i dati del prodotto,
<b>2.</b> le garanzie (l'RC obbligatoria con il premio base e quelle facoltative), <b>3.</b> come si può pagare (annuale, semestrale, mensile) e per quanti anni,
e, se servono, <b>4.</b> promozioni e <b>5.</b> prezzi diversi per età, zona o veicolo. Il preventivo segue lo stesso ordine.</p>

<section class="box">
  <h2>1. Dati del prodotto</h2>
  <form method="post" class="form">
    <?= Auth::campoCsrf() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="form--grid2">
      <label>Compagnia<select name="compagnia_id" required><option value="">Scegli…</option><?php foreach ($compagnie as $k => $v): ?><option value="<?= (int)$k ?>"<?= (int)$f['compagnia_id'] === (int)$k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
      <label>Nome del prodotto<input name="nome" maxlength="120" required value="<?= e($f['nome']) ?>"></label>
      <label>Classificazione<select name="categoria_id" required data-classificazione><option value="">Scegli…</option><?= Categorie::opzioni($f['categoria_id'] !== null ? (int)$f['categoria_id'] : null) ?></select></label>
      <label>Descrizione<input name="descrizione" maxlength="255" value="<?= e((string)$f['descrizione']) ?>"></label>
      <label data-solo-auto>Veicoli assicurati<select name="veicoli"><?php foreach (VEICOLI as $k => $l): ?><option value="<?= $k ?>"<?= $f['veicoli'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <div></div>
      <label>Premio minimo annuo (€)<input name="premio_minimo" inputmode="decimal" value="<?= e(nd($f['premio_minimo'])) ?>"><small class="hint">Vuoto = nessun minimo</small></label>
      <label>Premio massimo annuo (€)<input name="premio_massimo" inputmode="decimal" value="<?= e(nd($f['premio_massimo'])) ?>"><small class="hint">Vuoto = nessun massimo</small></label>
    </div>
    <label class="check"><input type="checkbox" name="attivo" value="1"<?= $f['attivo'] ? ' checked' : '' ?>> Attivo</label>
    <label class="check"><input type="checkbox" name="preventivabile" value="1"<?= $f['preventivabile'] ? ' checked' : '' ?><?= $p ? '' : ' disabled' ?>> Preventivabile online: entra nel calcolo del sito e del pannello <?= $p ? '' : '<small>(attivalo dopo aver inserito le garanzie)</small>' ?></label>
    <div class="actions"><button class="btn" name="azione" value="dati"><?= $p ? 'Salva i dati' : 'Crea il prodotto' ?></button></div>
  </form>
</section>

<?php if ($p):
    $gar = Preventivatore::garanzie($id, false);
    $hasRc = (bool)array_filter($gar, fn($g) => (int)$g['principale'] === 1);
    $st = db()->prepare('SELECT frazionamento, variazione FROM prodotti_frazionamenti WHERE prodotto_id = ?'); $st->execute([$id]); $fraz = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    $st = db()->prepare('SELECT anni, sconto FROM prodotti_durate WHERE prodotto_id = ?'); $st->execute([$id]); $dur = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    $st = db()->prepare('SELECT * FROM promozioni WHERE prodotto_id = ? ORDER BY id'); $st->execute([$id]); $promo = $st->fetchAll();
    $st = db()->prepare('SELECT * FROM prodotti_correzioni WHERE prodotto_id = ? ORDER BY tipo, id'); $st->execute([$id]); $corr = $st->fetchAll();
    $marche = db()->query("SELECT id, CONCAT(nome, IF(tipo_veicolo = 'moto', ' (moto)', '')) FROM marche ORDER BY tipo_veicolo, nome")->fetchAll(PDO::FETCH_KEY_PAIR);
    $oggi = date('Y-m-d'); ?>

<section class="box" id="garanzie">
  <h2>2. Garanzie e tariffe</h2>
  <p class="muted">La <b>responsabilità civile (RC)</b> è obbligatoria ed è sempre inclusa: il suo importo è il premio base, che varia con comune, marca e modello del veicolo.
  Le altre garanzie (furto, incendio, cristalli…) sono <b>facoltative</b> e si aggiungono solo insieme all'RC: non si può fare una polizza con solo furto e incendio.</p>
  <form method="post" class="form">
    <?= Auth::campoCsrf() ?><input type="hidden" name="id" value="<?= $id ?>">
    <div class="scroll-x"><table class="table table--edit table--garanzie"><thead><tr><th>Garanzia</th><th>Come si calcola</th><th class="right">Valore</th><th>Varia con zona e veicolo</th><th>Attiva</th><th></th></tr></thead><tbody>
    <?php
    $righe = $gar;
    if (!$hasRc) array_unshift($righe, ['id' => -1, 'codice' => 'RC', 'nome' => 'Responsabilità civile', 'descrizione' => '', 'principale' => 1, 'tipo_calcolo' => 'fisso', 'valore' => '', 'applica_coefficienti' => 1, 'ordine' => 0, 'attivo' => 1]);
    $righe[] = ['id' => -2, 'codice' => '', 'nome' => '', 'descrizione' => '', 'principale' => 0, 'tipo_calcolo' => 'fisso', 'valore' => '', 'applica_coefficienti' => 0, 'ordine' => 0, 'attivo' => 1];
    foreach ($righe as $pos => $g): $k = (int)$g['id']; $rc = (int)$g['principale'] === 1; $n = "g[$k]"; ?>
      <tr<?= $k === -2 ? ' class="row-new"' : '' ?>>
        <td><input type="hidden" name="<?= $n ?>[codice]" value="<?= e($g['codice']) ?>"><input type="hidden" name="<?= $n ?>[ordine]" value="<?= $rc ? 0 : ($pos + 1) * 10 ?>">
          <?php if ($k === -2): ?><b class="nuova">+ Aggiungi una garanzia facoltativa</b><?php endif; ?>
          <input name="<?= $n ?>[nome]" value="<?= e($g['nome']) ?>" maxlength="80" aria-label="Nome della garanzia"><?php if ($rc): ?><small class="hint">Obbligatoria, sempre inclusa</small><?php elseif ($k === -2): ?><small class="hint">Nome, es. Assistenza stradale</small><?php endif; ?>
          <input name="<?= $n ?>[descrizione]" value="<?= e((string)$g['descrizione']) ?>" maxlength="255" aria-label="Descrizione" class="sub"><small class="hint">Descrizione per il cliente (facoltativa)</small></td>
        <td><?php if ($rc): ?>Premio base in €<?php else: ?><select name="<?= $n ?>[tipo_calcolo]" aria-label="Come si calcola"><?php foreach (CALCOLI as $ck => $cl): ?><option value="<?= $ck ?>"<?= $g['tipo_calcolo'] === $ck ? ' selected' : '' ?>><?= e($cl) ?></option><?php endforeach; ?></select><?php endif; ?></td>
        <td><input name="<?= $n ?>[valore]" value="<?= e(nd((string)$g['valore'])) ?>" inputmode="decimal" class="right" size="8" aria-label="Valore"></td>
        <td><?= $rc ? 'sempre' : '<input type="checkbox" name="' . $n . '[coeff]" value="1"' . ((int)$g['applica_coefficienti'] ? ' checked' : '') . ' aria-label="Varia con zona e veicolo"><small class="hint">Solo per gli importi fissi</small>' ?></td>
        <td><?= $rc ? 'sì' : '<input type="checkbox" name="' . $n . '[attivo]" value="1"' . ((int)$g['attivo'] ? ' checked' : '') . ' aria-label="Attiva">' ?></td>
        <td><?php if (!$rc && $k > 0): ?><label class="check"><input type="checkbox" name="<?= $n ?>[togli]" value="1"> togli</label><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <div class="actions"><button class="btn" name="azione" value="garanzie">Salva le garanzie</button></div>
  </form>
</section>

<section class="box" id="pagamento">
  <h2>3. Pagamento e annualità</h2>
  <form method="post" class="form">
    <?= Auth::campoCsrf() ?><input type="hidden" name="id" value="<?= $id ?>">
    <div class="cols">
      <fieldset><legend>Frazionamenti ammessi</legend>
        <p class="muted">Variazione sul premio annuo: + maggiorazione, − sconto (es. 3 = +3% per il semestrale).</p>
        <table class="table table--small"><tbody>
        <?php foreach (Preventivatore::FRAZIONAMENTI as $k => $l): ?>
          <tr><td><label class="check"><input type="checkbox" name="fraz[<?= $k ?>]" value="1"<?= array_key_exists($k, $fraz) ? ' checked' : '' ?>> <?= e($l) ?></label></td>
          <td><input name="var[<?= $k ?>]" value="<?= e(nd($fraz[$k] ?? '0')) ?>" inputmode="decimal" class="right" size="5" aria-label="Variazione <?= e($l) ?>"> %</td></tr>
        <?php endforeach; ?>
        </tbody></table>
      </fieldset>
      <fieldset><legend>Durata del contratto</legend>
        <p class="muted">Sconto sul premio annuo per i contratti pluriennali.</p>
        <table class="table table--small"><tbody>
          <tr><td>1 anno</td><td class="muted">sempre</td></tr>
        <?php for ($a = 2; $a <= 5; $a++): ?>
          <tr><td><label class="check"><input type="checkbox" name="dur[<?= $a ?>]" value="1"<?= array_key_exists($a, $dur) ? ' checked' : '' ?>> <?= $a ?> anni</label></td>
          <td>sconto <input name="sconto[<?= $a ?>]" value="<?= e(nd($dur[$a] ?? '0')) ?>" inputmode="decimal" class="right" size="5" aria-label="Sconto <?= $a ?> anni"> %</td></tr>
        <?php endfor; ?>
        </tbody></table>
      </fieldset>
    </div>
    <div class="actions"><button class="btn" name="azione" value="pagamento">Salva pagamento e durata</button></div>
  </form>
</section>

<section class="box" id="promozioni">
  <h2>4. Promozioni <span class="muted">(facoltative)</span></h2>
  <p class="muted">Sconti applicati al premio annuo nel periodo indicato (date vuote = sempre). Si sommano in ordine, prima dello sconto per la durata e dei limiti di premio.</p>
  <form method="post" class="form">
    <?= Auth::campoCsrf() ?><input type="hidden" name="id" value="<?= $id ?>">
    <div class="scroll-x"><table class="table table--edit"><thead><tr><th>Nome</th><th>Tipo</th><th class="right">Valore</th><th>Dal</th><th>Al</th><th>Attiva</th><th></th></tr></thead><tbody>
    <?php foreach (array_merge($promo, [['id' => 0, 'nome' => '', 'tipo' => 'percentuale', 'valore' => '', 'dal' => null, 'al' => null, 'attivo' => 1]]) as $r): $k = (int)$r['id']; $n = "pr[$k]";
        $inCorso = $k && $r['attivo'] && (!$r['dal'] || $r['dal'] <= $oggi) && (!$r['al'] || $r['al'] >= $oggi); ?>
      <tr<?= $k ? '' : ' class="row-new"' ?>>
        <td><input name="<?= $n ?>[nome]" value="<?= e($r['nome']) ?>" maxlength="120" aria-label="Nome"><?= $k ? '' : '<small class="hint">Nome della nuova promozione</small>' ?><?= $inCorso ? ' <span class="tag tag--ok">in corso</span>' : '' ?></td>
        <td><select name="<?= $n ?>[tipo]" aria-label="Tipo"><option value="percentuale"<?= $r['tipo'] === 'percentuale' ? ' selected' : '' ?>>%</option><option value="importo"<?= $r['tipo'] === 'importo' ? ' selected' : '' ?>>€</option></select></td>
        <td><input name="<?= $n ?>[valore]" value="<?= e(nd((string)$r['valore'])) ?>" inputmode="decimal" class="right" size="6" aria-label="Valore"></td>
        <td><input type="date" name="<?= $n ?>[dal]" value="<?= e((string)$r['dal']) ?>" aria-label="Dal"></td>
        <td><input type="date" name="<?= $n ?>[al]" value="<?= e((string)$r['al']) ?>" aria-label="Al"></td>
        <td><input type="checkbox" name="<?= $n ?>[attivo]" value="1"<?= $r['attivo'] ? ' checked' : '' ?> aria-label="Attiva"></td>
        <td><?php if ($k): ?><label class="check"><input type="checkbox" name="<?= $n ?>[togli]" value="1"> togli</label><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <div class="actions"><button class="btn" name="azione" value="promozioni">Salva le promozioni</button></div>
  </form>
</section>

<section class="box" id="correzioni">
  <h2>5. Prezzo diverso per età, zona o veicolo <span class="muted">(facoltativo)</span></h2>
  <p class="muted">Facoltativo. Alza o abbassa il prezzo di questo prodotto in certi casi, in percentuale. Esempi: età da 18 a 25 anni +20; provincia TO +5; regione Piemonte −5; moto −20.</p>
  <form method="post" class="form">
    <?= Auth::campoCsrf() ?><input type="hidden" name="id" value="<?= $id ?>">
    <div class="scroll-x"><table class="table table--edit table--correzioni"><thead><tr><th>Quando</th><th>Condizione</th><th class="right">Variazione %</th><th>Nota</th><th></th></tr></thead><tbody>
    <?php foreach (array_merge($corr, [['id' => 0, 'tipo' => '', 'eta_min' => null, 'eta_max' => null, 'chiave' => '', 'moltiplicatore' => '', 'descrizione' => '']]) as $r): $k = (int)$r['id']; $n = "co[$k]";
        $pct = $r['moltiplicatore'] === '' ? '' : nd((string)round(((float)$r['moltiplicatore'] - 1) * 100, 2)); ?>
      <tr<?= $k ? '' : ' class="row-new"' ?> data-correzione>
        <td><select name="<?= $n ?>[tipo]" aria-label="Quando"><option value=""><?= $k ? '' : '+ Aggiungi…' ?></option><?php foreach (CORREZIONI as $ck => $cl): ?><option value="<?= $ck ?>"<?= $r['tipo'] === $ck ? ' selected' : '' ?>><?= e($cl) ?></option><?php endforeach; ?></select></td>
        <td>
          <span class="cond" data-per="eta"><span class="eta">da <input name="<?= $n ?>[eta_min]" value="<?= e((string)$r['eta_min']) ?>" inputmode="numeric" size="3" aria-label="Età da"> a <input name="<?= $n ?>[eta_max]" value="<?= e((string)$r['eta_max']) ?>" inputmode="numeric" size="3" aria-label="Età a"> anni</span></span>
          <span class="cond" data-per="provincia regione"><input name="<?= $n ?>[chiave]" value="<?= in_array($r['tipo'], ['provincia', 'regione'], true) ? e((string)$r['chiave']) : '' ?>" maxlength="60" size="14" aria-label="Provincia o regione"><small class="hint">Sigla della provincia (es. TO) o nome della regione</small></span>
          <span class="cond" data-per="marca"><select name="<?= $n ?>[marca]" aria-label="Marca"><option value="">Scegli…</option><?php foreach ($marche as $mk => $ml): ?><option value="<?= (int)$mk ?>"<?= $r['tipo'] === 'marca' && (int)$r['chiave'] === (int)$mk ? ' selected' : '' ?>><?= e($ml) ?></option><?php endforeach; ?></select></span>
          <span class="cond" data-per="veicolo"><select name="<?= $n ?>[veicolo]" aria-label="Tipo di veicolo"><option value="auto"<?= $r['chiave'] === 'auto' ? ' selected' : '' ?>>Auto</option><option value="moto"<?= $r['chiave'] === 'moto' ? ' selected' : '' ?>>Moto</option></select></span>
          <span class="cond muted" data-per="">Scegli prima "Quando"</span>
        </td>
        <td><input name="<?= $n ?>[variazione]" value="<?= e($pct) ?>" inputmode="decimal" class="right" size="6" aria-label="Variazione percentuale"><small class="hint">Es. 10 = +10%, -5 = −5%</small></td>
        <td><input name="<?= $n ?>[descrizione]" value="<?= e((string)$r['descrizione']) ?>" maxlength="120" aria-label="Nota"></td>
        <td><?php if ($k): ?><label class="check"><input type="checkbox" name="<?= $n ?>[togli]" value="1"> togli</label><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <div class="actions"><button class="btn" name="azione" value="correzioni">Salva</button></div>
  </form>
</section>

<div class="actions actions--wrap">
  <a class="btn btn--ghost" href="prova.php">Prova il calcolo</a>
  <form method="post" class="inline"><?= Auth::campoCsrf() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn--ghost" name="azione" value="duplica">Duplica il prodotto</button></form>
</div>
<form method="post" class="danger" data-conferma="Eliminare definitivamente questo prodotto con garanzie, promozioni e correzioni?">
  <?= Auth::campoCsrf() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="azione" value="elimina">
  <button class="btn btn--danger">Elimina il prodotto</button>
</form>
<?php endif;
pagina_fine();
