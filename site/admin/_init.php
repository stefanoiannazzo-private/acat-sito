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
        . '<label>Descrizione (facoltativa)<input name="descrizione" maxlength="190" placeholder="Es. Contratto firmato, libretto, carta d\'identità"></label>'
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
        'prova' => ['prova.php', 'Prova calcolo'],
        'garanzie' => ['tabella.php?t=garanzie', 'Garanzie'],
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
