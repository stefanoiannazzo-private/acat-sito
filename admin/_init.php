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
<?php foreach ($_SESSION['flash'] ?? [] as [$t, $m]): ?>
  <div class="flash flash--<?= e($t) ?>" role="status"><?= e($m) ?></div>
<?php endforeach; unset($_SESSION['flash']);
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
    return [
        'comune_id' => (int)($_POST['comune_id'] ?? $base['comune_id'] ?? 0), 'comune' => (string)($_POST['comune'] ?? $base['comune'] ?? ''),
        'nascita' => (string)($_POST['nascita'] ?? $base['nascita'] ?? ''), 'marca_id' => (int)($_POST['marca_id'] ?? 0), 'modello_id' => (int)($_POST['modello_id'] ?? 0),
        'garanzie' => array_values(array_filter(array_map(fn($x) => strtoupper(trim((string)$x)), (array)($_POST['garanzie'] ?? [])), fn($x) => (bool)preg_match('/^[A-Z0-9_-]{1,30}$/', $x))),
        'frazionamento' => isset(Preventivatore::FRAZIONAMENTI[$fraz]) ? $fraz : 'annuale', 'durata' => max(1, min(10, (int)($_POST['durata'] ?? 1))),
        'prodotto_id' => (int)($_POST['prodotto_id'] ?? 0),
    ];
}

function calcola_da_input(array $in): array
{
    $pag = ['frazionamento' => $in['frazionamento'], 'durata' => $in['durata']];
    if ($in['prodotto_id']) $pag['prodotto_id'] = $in['prodotto_id'];
    return Preventivatore::calcola($in['comune_id'], $in['nascita'], $in['modello_id'], $in['garanzie'], true, $pag);
}

/** Garanzie (per codice), pagamento, durata e scelta del prodotto */
function campi_calcolo(array $in): void
{
    $g = Preventivatore::garanziePubbliche();
    $prodotti = db()->query("SELECT p.id, CONCAT(c.nome, ' – ', p.nome, IF(p.preventivabile = 1, '', ' (non in tariffa online)')) FROM prodotti p JOIN compagnie c ON c.id = p.compagnia_id
        WHERE EXISTS (SELECT 1 FROM garanzie g WHERE g.prodotto_id = p.id AND g.principale = 1) ORDER BY p.preventivabile DESC, c.nome, p.nome")->fetchAll(PDO::FETCH_KEY_PAIR); ?>
  <fieldset><legend>Garanzie</legend>
  <?php if (!$g): ?><p class="muted">Nessun prodotto in tariffa online: apri un prodotto auto e spunta "Preventivabile online".</p><?php endif; ?>
  <?php foreach ($g as $x): ?>
    <label class="check"><input type="checkbox" name="garanzie[]" value="<?= e($x['codice']) ?>"<?= $x['principale'] ? ' checked disabled' : (in_array($x['codice'], $in['garanzie'], true) ? ' checked' : '') ?>> <?= e($x['nome']) ?></label>
  <?php endforeach; ?>
  </fieldset>
  <div class="form--grid2">
    <label>Pagamento<select name="frazionamento"><?php foreach (Preventivatore::FRAZIONAMENTI as $k => $l): ?><option value="<?= $k ?>"<?= $in['frazionamento'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label>Durata<select name="durata"><?php for ($a = 1; $a <= 5; $a++): ?><option value="<?= $a ?>"<?= $in['durata'] === $a ? ' selected' : '' ?>><?= $a === 1 ? '1 anno' : "$a anni" ?></option><?php endfor; ?></select></label>
  </div>
  <label>Prodotto<select name="prodotto_id"><option value="">Tutti i prodotti in tariffa: il prezzo migliore</option>
    <?php foreach ($prodotti as $k => $v): ?><option value="<?= (int)$k ?>"<?= $in['prodotto_id'] === (int)$k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
<?php }

/** Tabella di confronto fra i prodotti calcolati; con $preventivoId ogni riga ha "Usa questa proposta" */
function tabella_confronto(array $d, ?int $preventivoId = null, ?int $scelto = null): void
{
    $c = $d['confronto'] ?? null;
    if (!$c) return; ?>
  <h3>Confronto fra i prodotti</h3>
  <table class="table table--small table--confronto"><thead><tr><th>Compagnia e prodotto</th><th class="right">Annuo</th><th class="right">Rata</th><?= $preventivoId ? '<th></th>' : '' ?></tr></thead><tbody>
  <?php foreach ($c['validi'] as $i => $v): ?>
    <tr<?= ($scelto ?? ($d['prodotto_id'] ?? 0)) === $v['prodotto_id'] ? ' class="tot"' : '' ?>><td><?= e($v['compagnia'] . ' – ' . $v['prodotto']) ?><?= $i === 0 ? ' <span class="tag tag--ok">migliore</span>' : '' ?>
      <?php $note = array_merge($v['correzioni'] ?? [], array_map(fn($s) => $s['nome'], $v['sconti'] ?? []), $v['limite'] ? [ucfirst($v['limite'])] : [], $v['su_richiesta'] ? ['alcune voci su richiesta'] : []); ?>
      <?php if ($note): ?><br><small class="muted"><?= e(implode(' · ', $note)) ?></small><?php endif; ?></td>
      <td class="right"><?= euro($v['annuo']) ?></td><td class="right nowrap"><?= $v['rate'] > 1 ? $v['rate'] . ' × ' . euro($v['rata']) . '<br><small class="muted">' . e($v['frazionamento']) . '</small>' : '<span class="muted">unica annuale</span>' ?></td>
      <?php if ($preventivoId): ?><td class="azione"><form method="post"><?= Auth::campoCsrf() ?><input type="hidden" name="id" value="<?= $preventivoId ?>"><input type="hidden" name="prodotto_id" value="<?= (int)$v['prodotto_id'] ?>"><button class="btn btn--ghost btn--small" name="azione" value="usa_proposta">Usa questa proposta</button></form></td><?php endif; ?></tr>
  <?php endforeach; ?>
  <?php foreach ($c['esclusi'] as $x): ?>
    <tr class="muted"><td><?= e($x['compagnia'] . ' – ' . $x['prodotto']) ?><br><small><?= e($x['motivo']) ?></small></td><td class="right">–</td><td class="right">–</td><?= $preventivoId ? '<td></td>' : '' ?></tr>
  <?php endforeach; ?>
  </tbody></table>
<?php }

