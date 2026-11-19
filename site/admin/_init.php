<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; style-src-attr 'unsafe-inline'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

Migrazioni::esegui(db());
if (impostazione('sito_url', '') !== EmailPreventivo::sitoUrl()) {
    db()->prepare("INSERT INTO impostazioni (chiave, valore, descrizione) VALUES ('sito_url', ?, 'Indirizzo del sito (usato nelle email inviate dal cron)') ON DUPLICATE KEY UPDATE valore = VALUES(valore)")->execute([EmailPreventivo::sitoUrl()]);
}
Auth::avvia();
Auth::verificaCsrf();

const STATI_PREVENTIVO = [
    'nuovo' => 'Da lavorare', 'in_lavorazione' => 'In lavorazione', 'inviato' => 'Inviato al cliente',
    'accettato' => 'Accettato', 'rifiutato' => 'Rifiutato', 'chiuso' => 'Chiuso',
];

function stato_preventivo(string $s): string
{
    return '<span class="tag tag--' . e($s) . '">' . e(STATI_PREVENTIVO[$s] ?? $s) . '</span>';
}

/** Elenco documenti con scarica / apri / elimina */
function docs_tabella(array $docs, ?int $polizza = null): void
{
    if (!$docs) { echo '<p class="muted">Nessun documento.</p>'; return; }
    echo '<div class="tablewrap"><table class="table table--small"><tbody>';
    foreach ($docs as $d) {
        $vedi = in_array($d['mime'], ['application/pdf', 'image/jpeg', 'image/png'], true);
        echo '<tr><td><a href="documenti.php?id=' . (int)$d['id'] . '">' . e($d['nome']) . '</a>'
            . ($d['descrizione'] ? '<br><small class="muted">' . e($d['descrizione']) . '</small>' : '')
            . (!$polizza && !empty($d['polizza_id']) ? '<br><small><a href="polizze.php?id=' . (int)$d['polizza_id'] . '">collegato a una polizza</a></small>' : '') . '</td>'
            . '<td class="muted">' . e(Documenti::mb((int)$d['dimensione'])) . '<br>' . e(data_it($d['creato_il'], false)) . '</td>'
            . '<td class="right nowrap">' . ($vedi ? '<a href="documenti.php?id=' . (int)$d['id'] . '&amp;vedi=1" target="_blank" rel="noopener">Apri</a> · ' : '')
            . '<a href="documenti.php?id=' . (int)$d['id'] . '">Scarica</a> · '
            . '<form method="post" action="documenti.php" class="inline" data-conferma="Eliminare il documento ' . e($d['nome']) . '?">' . Auth::campoCsrf()
            . '<input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="' . (int)$d['id'] . '">' . ($polizza ? '<input type="hidden" name="polizza" value="' . $polizza . '">' : '')
            . '<button class="linkbtn">Elimina</button></form></td></tr>';
    }
    echo '</tbody></table></div>';
}

/** Modulo di caricamento; con $conLettura propone di leggere il contratto e compilare la polizza */
function docs_form(int $cliente, ?int $polizza = null, bool $conLettura = true): void
{
    $max = Documenti::maxByte();
    echo '<form method="post" action="documenti.php" enctype="multipart/form-data" class="form upload">' . Auth::campoCsrf()
        . '<input type="hidden" name="azione" value="carica"><input type="hidden" name="cliente_id" value="' . $cliente . '">'
        . ($polizza ? '<input type="hidden" name="polizza_id" value="' . $polizza . '">' : '')
        . '<input type="hidden" name="MAX_FILE_SIZE" value="' . $max . '">'
        . '<label>Carica un documento<input type="file" name="file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" data-max="' . $max . '"><small>PDF, Word, JPG o PNG · massimo ' . e(Documenti::mb($max)) . '</small></label>'
        . '<label>Descrizione (facoltativa)<input name="descrizione" maxlength="190"><small class="hint">Es. contratto firmato, libretto, carta d\'identità</small></label>'
        . ($conLettura ? '<label class="check"><input type="checkbox" name="leggi" value="1"> È un contratto: leggi le date e ' . ($polizza ? 'proponile per questa polizza' : 'compila una nuova polizza') . ' (PDF digitali e Word)</label>' : '')
        . '<div class="actions"><button class="btn btn--ghost">Carica</button></div></form>';
}

function flash(string $tipo, string $msg): void { $_SESSION['flash'][] = [$tipo, $msg]; }

function vai(string $url): never { header('Location: ' . $url); exit; }

function pagina_inizio(string $titolo, string $sezione = ''): void
{
    $u = Auth::utente();
    $menu = [
        'index' => ['index.php', 'Riepilogo'],
        'clienti' => ['clienti.php', 'Clienti'],
        'preventivi' => ['preventivi.php', 'Preventivi'],
        'polizze' => ['polizze.php', 'Scadenziario polizze'],
        'compagnie' => ['tabella.php?t=compagnie', 'Compagnie'],
        'prodotti' => ['tabella.php?t=prodotti', 'Prodotti'],
        'categorie' => ['tabella.php?t=categorie', 'Classificazione polizze'],
        'prova' => ['prova.php', 'Prova calcolo'],
        'fasce_eta' => ['tabella.php?t=fasce_eta', 'Fasce d\'età'],
        'comuni' => ['tabella.php?t=comuni', 'Comuni'],
        'marche' => ['tabella.php?t=marche', 'Marche'],
        'modelli' => ['tabella.php?t=modelli', 'Modelli'],
        'stati_cliente' => ['tabella.php?t=stati_cliente', 'Stati dei clienti'],
        'impostazioni' => ['impostazioni.php', 'Impostazioni'],
    ];
    if (($u['ruolo'] ?? '') === 'admin') $menu['utenti'] = ['utenti.php', 'Utenti'];
    ?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titolo) ?> · Pannello ACAT</title>
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="admin.css?v=<?= e((string)@filemtime(__DIR__ . '/admin.css')) ?>">
<script src="admin.js?v=<?= e((string)@filemtime(__DIR__ . '/admin.js')) ?>" defer></script>
</head>
<body>
<?php if ($u): ?>
<header class="top">
  <a class="top__logo" href="index.php"><img src="../logo-acat.svg" alt="ACAT Servizi" width="120" height="25"><span>Preventivatore</span></a>
  <button class="top__menu" type="button" aria-expanded="false" aria-controls="side">Menu</button>
  <div class="top__user"><a href="profilo.php"><?= e($u['nome']) ?></a> · <form method="post" action="logout.php" class="inline"><?= Auth::campoCsrf() ?><button class="linkbtn">Esci</button></form></div>
</header>
<div class="shell">
<nav class="side" id="side" aria-label="Sezioni">
<?php foreach ($menu as $k => [$href, $lab]): ?>
  <a href="<?= e($href) ?>"<?= $k === $sezione ? ' aria-current="page"' : '' ?>><?= e($lab) ?></a>
<?php endforeach; ?>
  <a href="../preventivo-rc-auto.html" target="_blank" rel="noopener" class="side__ext">Apri il preventivatore ↗</a>
</nav>
<main class="main">
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="avvisi" aria-live="polite">
  <?php foreach ($_SESSION['flash'] as [$t, $m]): ?>
    <div class="flash flash--<?= e($t) ?> avviso" role="<?= $t === 'errore' ? 'alert' : 'status' ?>"><span><?= e($m) ?></span><button type="button" class="avviso__chiudi" aria-label="Chiudi">×</button></div>
  <?php endforeach; ?>
  </div>
<?php endif; unset($_SESSION['flash']);
}

function pagina_fine(): void
{
    $u = Auth::utente();
    if ($u) echo "</main>\n</div>\n";
    echo "</body>\n</html>\n";
}

function paginazione(int $tot, int $pag, int $per, array $q): string
{
    $n = (int)ceil($tot / $per);
    if ($n <= 1) return '';
    $out = '<nav class="pager" aria-label="Pagine">';
    $link = fn($p, $lab, $cur = false) => '<a href="?' . e(http_build_query(array_merge($q, ['p' => $p]))) . '"' . ($cur ? ' aria-current="page"' : '') . '>' . $lab . '</a>';
    if ($pag > 1) $out .= $link($pag - 1, '‹ Precedente');
    $out .= '<span>Pagina ' . $pag . ' di ' . $n . '</span>';
    if ($pag < $n) $out .= $link($pag + 1, 'Successiva ›');
    return $out . '</nav>';
}

/* ---------- calcolo: campi comuni a "Prova calcolo" e "Nuovo preventivo" ---------- */

/** Dati del modulo di calcolo (POST), con i valori iniziali in $base */
function calcolo_input(array $base = []): array
{
    $fraz = (string)($_POST['frazionamento'] ?? 'annuale');
    $comp = (string)($_POST['compagnia'] ?? '');
    return [
        'comune_id' => (int)($_POST['comune_id'] ?? $base['comune_id'] ?? 0), 'comune' => (string)($_POST['comune'] ?? $base['comune'] ?? ''),
        'nascita' => (string)($_POST['nascita'] ?? $base['nascita'] ?? ''), 'marca_id' => (int)($_POST['marca_id'] ?? 0), 'modello_id' => (int)($_POST['modello_id'] ?? 0),
        'garanzie' => array_values(array_filter(array_map(fn($x) => strtoupper(trim((string)$x)), (array)($_POST['garanzie'] ?? [])), fn($x) => (bool)preg_match('/^[A-Z0-9_-]{1,30}$/', $x))),
        'frazionamento' => isset(Preventivatore::FRAZIONAMENTI[$fraz]) ? $fraz : 'annuale', 'durata' => max(1, min(10, (int)($_POST['durata'] ?? 1))),
        'categoria_id' => (int)($_POST['categoria_id'] ?? 0),
        'compagnia' => $comp === 'tutte' ? 'tutte' : (string)(int)$comp,
        'prodotto_id' => (int)($_POST['prodotto_id'] ?? 0),
    ];
}

/** Prodotti con una tariffa (RC con premio base) dell'area Auto, con garanzie, frazionamenti e durate: guidano il modulo passo per passo */
function prodotti_tariffati(): array
{
    $auto = Categorie::perChiave('auto');
    $rows = db()->query("SELECT p.id, p.nome, p.categoria_id, p.compagnia_id, c.nome AS compagnia, p.preventivabile
        FROM prodotti p JOIN compagnie c ON c.id = p.compagnia_id LEFT JOIN categorie k ON k.id = p.categoria_id
        WHERE p.attivo = 1 AND c.attivo = 1 AND p.categoria_id IS NOT NULL" . ($auto ? ' AND COALESCE(k.parent_id, k.id) = ' . (int)$auto : '') . "
          AND EXISTS (SELECT 1 FROM garanzie g WHERE g.prodotto_id = p.id AND g.principale = 1 AND g.attivo = 1)
        ORDER BY c.nome, p.nome")->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[(int)$r['id']] = ['id' => (int)$r['id'], 'nome' => $r['nome'], 'categoria' => (int)$r['categoria_id'], 'compagnia_id' => (int)$r['compagnia_id'],
        'compagnia' => $r['compagnia'], 'garanzie' => [], 'frazionamenti' => [], 'durate' => [1]];
    if (!$out) return [];
    $in = implode(',', array_keys($out));
    foreach (db()->query("SELECT prodotto_id, codice, nome, principale FROM garanzie WHERE attivo = 1 AND prodotto_id IN ($in) ORDER BY principale DESC, ordine, nome") as $g)
        $out[(int)$g['prodotto_id']]['garanzie'][] = ['codice' => strtoupper($g['codice']), 'nome' => $g['nome'], 'rc' => (int)$g['principale'] === 1];
    foreach (db()->query("SELECT prodotto_id, frazionamento FROM prodotti_frazionamenti WHERE prodotto_id IN ($in)") as $f) $out[(int)$f['prodotto_id']]['frazionamenti'][] = $f['frazionamento'];
    foreach (db()->query("SELECT prodotto_id, anni FROM prodotti_durate WHERE anni > 1 AND prodotto_id IN ($in) ORDER BY anni") as $d) $out[(int)$d['prodotto_id']]['durate'][] = (int)$d['anni'];
    foreach ($out as &$p) if (!$p['frazionamenti']) $p['frazionamenti'] = ['annuale'];
    return array_values($out);
}

function calcola_da_input(array $in): array
{
    $pag = ['frazionamento' => $in['frazionamento'], 'durata' => $in['durata']];
    $prodotti = array_values(array_filter(prodotti_tariffati(), fn($p) => $p['categoria'] === $in['categoria_id']));
    if (!$in['categoria_id'] || !$prodotti) return [null, 'Scegli il tipo di polizza.'];
    if ($in['compagnia'] === '' || $in['compagnia'] === '0') return [null, 'Scegli la compagnia (oppure "Confronta tutte").'];
    if ($in['compagnia'] !== 'tutte') $prodotti = array_values(array_filter($prodotti, fn($p) => $p['compagnia_id'] === (int)$in['compagnia']));
    $ids = array_column($prodotti, 'id');
    if ($in['prodotto_id'] && in_array($in['prodotto_id'], $ids, true)) $pag['prodotto_id'] = $in['prodotto_id'];
    elseif (count($ids) === 1) $pag['prodotto_id'] = $ids[0];
    elseif ($ids) $pag['prodotti'] = $ids;
    else return [null, 'Questa compagnia non ha tariffe per il tipo di polizza scelto.'];
    return Preventivatore::calcola($in['comune_id'], $in['nascita'], $in['modello_id'], $in['garanzie'], true, $pag);
}

/** Passi del calcolo: tipo di polizza → compagnia (→ prodotto) → garanzie (RC sempre inclusa) → pagamento → durata */
function campi_calcolo(array $in): void
{
    $prodotti = prodotti_tariffati();
    $cat = array_unique(array_column($prodotti, 'categoria'));
    $scelte = ['categoria_id' => $in['categoria_id'], 'compagnia' => $in['compagnia'], 'prodotto_id' => $in['prodotto_id'], 'garanzie' => $in['garanzie'], 'frazionamento' => $in['frazionamento'], 'durata' => $in['durata']]; ?>
  <fieldset class="passo"><legend><span>1</span> Tipo di polizza</legend>
    <?php if (!$prodotti): ?><p class="err">Non c'è ancora nessun prodotto auto con una tariffa: apri Prodotti, scegli un prodotto auto e inserisci l'RC con il premio base.</p><?php endif; ?>
    <label>Tipo di polizza<select name="categoria_id" id="calc-tipo" required><option value="">Scegli…</option>
      <?php foreach ($cat as $k): ?><option value="<?= $k ?>"<?= $in['categoria_id'] === $k ? ' selected' : '' ?>><?= e(Categorie::etichetta($k)) ?></option><?php endforeach; ?></select>
      <small class="hint">Es. RC auto e moto (Bonus Malus): comprende sempre la responsabilità civile</small></label>
  </fieldset>
  <fieldset class="passo"><legend><span>2</span> Compagnia</legend>
    <label>Compagnia<select name="compagnia" id="calc-compagnia" required><option value="">Scegli prima il tipo di polizza</option></select>
      <small class="hint">"Confronta tutte" calcola ogni compagnia e propone il prezzo migliore</small></label>
    <label id="calc-prodotto-box" hidden>Prodotto della compagnia<select name="prodotto_id" id="calc-prodotto"></select>
      <small class="hint">La compagnia ha più prodotti per questo tipo di polizza</small></label>
  </fieldset>
  <fieldset class="passo"><legend><span>3</span> Garanzie</legend>
    <div id="calc-garanzie"><p class="muted">Scegli tipo di polizza e compagnia.</p></div>
  </fieldset>
  <fieldset class="passo"><legend><span>4</span> Pagamento e annualità</legend>
    <div class="form--grid2">
      <label>Frazionamento<select name="frazionamento" id="calc-fraz"></select></label>
      <label>Annualità (durata del contratto)<select name="durata" id="calc-durata"></select></label>
    </div>
  </fieldset>
  <script type="application/json" id="calc-dati"><?= json_encode(['prodotti' => $prodotti, 'scelte' => $scelte, 'nomi_fraz' => Preventivatore::FRAZIONAMENTI], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php }

/** Tabella di confronto fra i prodotti calcolati; con $preventivoId ogni riga ha "Usa questa proposta" */
function tabella_confronto(array $d, ?int $preventivoId = null, ?int $scelto = null): void
{
    $c = $d['confronto'] ?? null;
    if (!$c) return; ?>
  <h3>Confronto fra i prodotti</h3>
  <table class="table table--small table--confronto"><thead><tr><th>Compagnia e prodotto</th><th class="right">Annuo</th><th class="right">Rata</th></tr></thead><tbody>
  <?php foreach ($c['validi'] as $i => $v): ?>
    <tr<?= ($scelto ?? ($d['prodotto_id'] ?? 0)) === $v['prodotto_id'] ? ' class="tot"' : '' ?>><td><?= e($v['compagnia'] . ' – ' . $v['prodotto']) ?><?= $i === 0 ? ' <span class="tag tag--ok">migliore</span>' : '' ?>
      <?php $note = array_merge($v['correzioni'] ?? [], array_map(fn($s) => $s['nome'], $v['sconti'] ?? []), $v['limite'] ? [ucfirst($v['limite'])] : [], $v['su_richiesta'] ? ['alcune voci su richiesta'] : []); ?>
      <?php if ($note): ?><br><small class="muted"><?= e(implode(' · ', $note)) ?></small><?php endif; ?></td>
      <td class="right nowrap"><?= euro($v['annuo']) ?></td><td class="right nowrap"><?= $v['rate'] > 1 ? $v['rate'] . ' × ' . euro($v['rata']) . '<br><small class="muted">' . e($v['frazionamento']) . '</small>' : '<span class="muted">unica annuale</span>' ?></td>
    </tr>
      <?php if ($preventivoId): ?><tr class="riga-azione"><td colspan="3"><form method="post"><?= Auth::campoCsrf() ?><input type="hidden" name="id" value="<?= $preventivoId ?>"><input type="hidden" name="prodotto_id" value="<?= (int)$v['prodotto_id'] ?>"><button class="btn btn--rosso" name="azione" value="usa_proposta">Usa questa proposta</button></form></td></tr><?php endif; ?>
  <?php endforeach; ?>
  <?php foreach ($c['esclusi'] as $x): ?>
    <tr class="muted"><td><?= e($x['compagnia'] . ' – ' . $x['prodotto']) ?><br><small><?= e($x['motivo']) ?></small></td><td class="right">–</td><td class="right">–</td></tr>
  <?php endforeach; ?>
  </tbody></table>
<?php }

/**
 * Garanzie proponibili nel preventivo: quelle del prodotto del preventivo e, per ogni compagnia,
 * quelle dei suoi prodotti dell'area Auto (se in agenzia si cambia compagnia, l'elenco segue).
 */
function garanzie_per_preventivo(array $p): array
{
    $auto = Categorie::perChiave('auto');
    $rows = db()->query("SELECT g.nome, g.tipo_calcolo, g.valore, g.prodotto_id, c.nome AS compagnia
        FROM garanzie g JOIN prodotti pr ON pr.id = g.prodotto_id JOIN compagnie c ON c.id = pr.compagnia_id
        LEFT JOIN categorie k ON k.id = pr.categoria_id
        WHERE g.attivo = 1 AND g.principale = 0 AND pr.attivo = 1" . ($auto ? ' AND (pr.categoria_id IS NULL OR COALESCE(k.parent_id, k.id) = ' . (int)$auto . ')' : '') . '
        ORDER BY g.ordine, g.nome')->fetchAll();
    $out = ['prodotto' => [], 'prodotto_compagnia' => null, 'compagnie' => []];
    foreach ($rows as $r) {
        $v = ['nome' => $r['nome'], 'tipo' => $r['tipo_calcolo'], 'valore' => (float)$r['valore']];
        if ($p['prodotto_id'] !== null && (int)$r['prodotto_id'] === (int)$p['prodotto_id']) { $out['prodotto'][] = $v; $out['prodotto_compagnia'] = $r['compagnia']; }
        $k = mb_strtolower(trim($r['compagnia']));
        $out['compagnie'][$k][mb_strtolower($r['nome'])] ??= $v;
    }
    foreach ($out['compagnie'] as $k => $l) $out['compagnie'][$k] = array_values($l);
    return $out;
}

