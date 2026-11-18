<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
Auth::richiedi();

$stati = ['nuovo' => 'Nuovo', 'contattato' => 'Contattato', 'chiuso' => 'Chiuso'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['azione'] ?? '') === 'stato' && isset($stati[$_POST['stato'] ?? ''])) {
        db()->prepare('UPDATE preventivi SET stato = ? WHERE id = ?')->execute([$_POST['stato'], $id]);
        Auth::log('stato', 'preventivi', $id, $_POST['stato']);
        flash('ok', 'Stato aggiornato.');
        vai('preventivi.php?id=' . $id);
    }
    if (($_POST['azione'] ?? '') === 'elimina') {
        db()->prepare('DELETE FROM preventivi WHERE id = ?')->execute([$id]);
        Auth::log('elimina', 'preventivi', $id);
        flash('ok', 'Richiesta eliminata.');
        vai('preventivi.php');
    }
}

$par = []; $w = ['1=1'];
if (isset($stati[$_GET['stato'] ?? ''])) { $w[] = 'stato = ?'; $par[] = $_GET['stato']; }
if (($q = trim((string)($_GET['q'] ?? ''))) !== '') { $w[] = '(nome LIKE ? OR email LIKE ? OR veicolo LIKE ? OR comune_nome LIKE ?)'; array_push($par, "%$q%", "%$q%", "%$q%", "%$q%"); }
$where = implode(' AND ', $w);

if (isset($_GET['esporta'])) {
    $st = db()->prepare("SELECT id, creato_il, stato, nome, email, telefono, comune_nome, data_nascita, eta, veicolo, garanzie, totale, note FROM preventivi WHERE $where ORDER BY id DESC");
    $st->execute($par);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="preventivi-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['n', 'data', 'stato', 'nome', 'email', 'telefono', 'comune', 'nascita', 'eta', 'veicolo', 'garanzie', 'totale', 'note'], ';', '"', '');
    while ($r = $st->fetch()) { $r['creato_il'] = data_it($r['creato_il']); $r['totale'] = str_replace('.', ',', (string)$r['totale']); fputcsv($o, $r, ';', '"', ''); }
    exit;
}

pagina_inizio('Preventivi ricevuti', 'preventivi');
if (isset($_GET['id'])):
    $st = db()->prepare('SELECT * FROM preventivi WHERE id = ?');
    $st->execute([(int)$_GET['id']]);
    $p = $st->fetch() ?: vai('preventivi.php');
    $d = json_decode((string)$p['dettaglio'], true) ?: []; ?>
  <p class="crumbs"><a href="preventivi.php">← Preventivi ricevuti</a></p>
  <h1>Richiesta n. <?= (int)$p['id'] ?> <span class="tag tag--<?= e($p['stato']) ?>"><?= e($stati[$p['stato']]) ?></span></h1>
  <div class="cols">
    <section class="box">
      <h2>Cliente</h2>
      <dl class="dl">
        <dt>Nome</dt><dd><?= e($p['nome']) ?></dd>
        <dt>Email</dt><dd><a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a></dd>
        <dt>Telefono</dt><dd><?= $p['telefono'] ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $p['telefono'])) . '">' . e($p['telefono']) . '</a>' : '–' ?></dd>
        <dt>Comune</dt><dd><?= e($p['comune_nome']) ?></dd>
        <dt>Nascita</dt><dd><?= e($p['data_nascita'] ? date('d/m/Y', strtotime($p['data_nascita'])) : '–') ?> (<?= (int)$p['eta'] ?> anni)</dd>
        <dt>Veicolo</dt><dd><?= e($p['veicolo']) ?></dd>
        <dt>Ricevuta</dt><dd><?= e(data_it($p['creato_il'])) ?></dd>
        <dt>Privacy</dt><dd><?= $p['consenso_privacy'] ? 'Consenso dato' : '–' ?></dd>
      </dl>
      <?php if ($p['note']): ?><h3>Note del cliente</h3><p class="pre"><?= e($p['note']) ?></p><?php endif; ?>
    </section>
    <section class="box">
      <h2>Stima calcolata</h2>
      <table class="table"><tbody>
      <?php foreach ($d['righe'] ?? [] as $r): ?><tr><td><?= e($r['nome']) ?><br><small class="muted"><?= e($r['spiegazione'] ?? '') ?></small></td><td class="right"><?= $r['importo'] === null ? 'su richiesta' : euro($r['importo']) ?></td></tr><?php endforeach; ?>
      <tr class="tot"><td>Totale stimato</td><td class="right"><?= euro($p['totale']) ?></td></tr>
      </tbody></table>
      <p class="muted">Calcolata con le tariffe in vigore al momento della richiesta<?= isset($d['fascia_eta']) ? ' · fascia: ' . e($d['fascia_eta']) : '' ?>.</p>
      <form method="post" class="form form--inline">
        <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="stato"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <label>Stato<select name="stato"><?php foreach ($stati as $k => $l): ?><option value="<?= $k ?>"<?= $p['stato'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
        <button class="btn">Aggiorna</button>
      </form>
    </section>
  </div>
  <form method="post" class="danger" data-conferma="Eliminare definitivamente questa richiesta e i dati del cliente?">
    <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <button class="btn btn--danger">Elimina la richiesta</button>
  </form>
<?php else:
    $per = 50; $pag = max(1, (int)($_GET['p'] ?? 1));
    $st = db()->prepare("SELECT COUNT(*) FROM preventivi WHERE $where"); $st->execute($par); $tot = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT id, creato_il, nome, email, veicolo, comune_nome, totale, stato FROM preventivi WHERE $where ORDER BY id DESC LIMIT $per OFFSET " . (($pag - 1) * $per));
    $st->execute($par);
    $qs = array_filter(['stato' => $_GET['stato'] ?? '', 'q' => $q]); ?>
  <div class="head"><h1>Preventivi ricevuti <span class="muted">(<?= $tot ?>)</span></h1>
    <div class="head__actions"><a class="btn btn--ghost" href="?<?= e(http_build_query($qs + ['esporta' => 1])) ?>">Esporta CSV</a></div></div>
  <form method="get" class="filters">
    <input type="search" name="q" placeholder="Nome, email, veicolo, comune…" value="<?= e($q) ?>" aria-label="Cerca">
    <select name="stato" aria-label="Stato"><option value="">Tutti gli stati</option><?php foreach ($stati as $k => $l): ?><option value="<?= $k ?>"<?= ($_GET['stato'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn btn--ghost">Filtra</button>
  </form>
  <div class="tablewrap"><table class="table">
    <thead><tr><th>N.</th><th>Data</th><th>Cliente</th><th>Veicolo</th><th>Comune</th><th class="right">Totale</th><th>Stato</th></tr></thead>
    <tbody>
    <?php foreach ($st as $p): ?>
      <tr><td><a href="?id=<?= (int)$p['id'] ?>"><?= (int)$p['id'] ?></a></td><td><?= e(data_it($p['creato_il'])) ?></td>
      <td><a href="?id=<?= (int)$p['id'] ?>"><?= e($p['nome']) ?></a><br><small class="muted"><?= e($p['email']) ?></small></td>
      <td><?= e($p['veicolo']) ?></td><td><?= e($p['comune_nome']) ?></td><td class="right"><?= euro($p['totale']) ?></td>
      <td><span class="tag tag--<?= e($p['stato']) ?>"><?= e($stati[$p['stato']]) ?></span></td></tr>
    <?php endforeach; ?>
    <?php if (!$tot): ?><tr><td colspan="7" class="muted">Nessuna richiesta.</td></tr><?php endif; ?>
    </tbody></table></div>
  <?= paginazione($tot, $pag, $per, $qs) ?>
<?php endif;
pagina_fine();
