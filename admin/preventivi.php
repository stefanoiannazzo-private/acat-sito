<?php
/* Preventivi: elenco, dettaglio, preventivo definitivo (importi, compagnia, validità), anteprima e invio al cliente. */
declare(strict_types=1);
require __DIR__ . '/_init.php';
$me = Auth::richiedi();

function carica(int $id): ?array
{
    $st = db()->prepare('SELECT p.*, c.nome AS cliente_nome, c.email AS cliente_email, c.telefono AS cliente_tel, c.stato_id AS cliente_stato, c.nome_proprio AS cliente_nome_proprio, c.tipo AS cliente_tipo FROM preventivi p LEFT JOIN clienti c ON c.id = p.cliente_id WHERE p.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* ---------- azioni ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $az = (string)($_POST['azione'] ?? '');
    $p = carica($id) ?? vai('preventivi.php');

    if ($az === 'elimina') {
        db()->prepare('DELETE FROM preventivi WHERE id = ?')->execute([$id]);
        Auth::log('elimina', 'preventivi', $id);
        flash('ok', 'Preventivo eliminato.');
        vai($p['cliente_id'] ? 'clienti.php?id=' . $p['cliente_id'] : 'preventivi.php');
    }

    if ($az === 'usa_proposta') {
        $d = json_decode((string)$p['dettaglio'], true) ?: [];
        $pid = (int)($_POST['prodotto_id'] ?? 0);
        foreach ($d['confronto']['validi'] ?? [] as $v) if ((int)$v['prodotto_id'] === $pid) {
            $righe = array_map(fn($r) => ['nome' => $r['nome'], 'importo' => $r['importo']], array_merge($v['righe'], $v['sconti'] ?? []));
            db()->prepare('UPDATE preventivi SET righe_definitive = ?, totale_definitivo = ?, compagnia = ?, prodotto_id = ?, frazionamento = ? WHERE id = ?')
                ->execute([json_encode($righe, JSON_UNESCAPED_UNICODE), $v['annuo'], $v['compagnia'], $pid, $v['frazionamento'], $id]);
            Auth::log('modifica', 'preventivi', $id, 'proposta ' . $v['compagnia'] . ' – ' . $v['prodotto']);
            flash('ok', 'Importi e compagnia presi dalla proposta ' . $v['compagnia'] . ' – ' . $v['prodotto'] . '.');
            vai('preventivi.php?id=' . $id);
        }
        flash('errore', 'Proposta non trovata nel confronto.');
        vai('preventivi.php?id=' . $id);
    }

    if (in_array($az, ['salva', 'invia'], true)) {
        // importi definitivi riga per riga (vuoto = "su richiesta")
        $base = EmailPreventivo::righe($p);
        $righe = []; $tot = 0.0; $err = [];
        foreach ($base as $i => $r) {
            $nome = trim(mb_substr((string)($_POST['riga_nome'][$i] ?? $r['nome']), 0, 80));
            $raw = trim((string)($_POST['riga_importo'][$i] ?? ''));
            $imp = $raw === '' ? null : num($raw);
            if ($raw !== '' && ($imp === null || $imp < 0)) $err[] = "Importo non valido per \"$nome\".";
            if (!empty($_POST['riga_togli'][$i]) && $i > 0) continue;
            $righe[] = ['nome' => $nome ?: $r['nome'], 'importo' => $imp === null ? null : round($imp, 2)];
            $tot += (float)$imp;
        }
        $nuova = trim(mb_substr((string)($_POST['nuova_nome'] ?? ''), 0, 80));
        if ($nuova !== '') {
            $imp = num((string)($_POST['nuova_importo'] ?? ''));
            if ($imp === null || $imp < 0) $err[] = 'Indica l\'importo della voce aggiunta.';
            else { $righe[] = ['nome' => $nuova, 'importo' => round($imp, 2)]; $tot += $imp; }
        }
        // la stessa garanzia non può comparire due volte
        $visti = [];
        foreach ($righe as $r) {
            $k = preg_replace('/\s+/u', ' ', mb_strtolower(trim($r['nome'])));
            if (isset($visti[$k])) $err[] = 'Garanzia già presente nel preventivo: "' . $r['nome'] . '".';
            $visti[$k] = true;
        }
        $decorrenza = trim((string)($_POST['decorrenza'] ?? '')) ?: null;
        $valido = trim((string)($_POST['valido_fino'] ?? '')) ?: null;
        foreach (['decorrenza' => $decorrenza, 'valido_fino' => $valido] as $k => $d) if ($d && !DateTimeImmutable::createFromFormat('!Y-m-d', $d)) $err[] = 'Data non valida.';
        $stato = (string)($_POST['stato'] ?? $p['stato']);
        if (!isset(STATI_PREVENTIVO[$stato])) $stato = $p['stato'];
        if ($err) { foreach ($err as $m) flash('errore', $m); vai('preventivi.php?id=' . $id); }

        db()->prepare('UPDATE preventivi SET righe_definitive = ?, totale_definitivo = ?, compagnia = ?, decorrenza = ?, valido_fino = ?, messaggio = ?, stato = ? WHERE id = ?')
            ->execute([json_encode($righe, JSON_UNESCAPED_UNICODE), round($tot, 2), trim(mb_substr((string)($_POST['compagnia'] ?? ''), 0, 80)) ?: null,
                $decorrenza, $valido, trim(mb_substr((string)($_POST['messaggio'] ?? ''), 0, 3000)) ?: null, $stato, $id]);
        Auth::log('modifica', 'preventivi', $id);

        if ($az === 'salva') { flash('ok', 'Preventivo salvato.'); vai('preventivi.php?id=' . $id); }

        // invio al cliente
        $p = carica($id);
        $to = (string)($p['cliente_email'] ?: $p['email']);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { flash('errore', 'Il cliente non ha un indirizzo email valido: aggiungilo nella sua scheda.'); vai('preventivi.php?id=' . $id); }
        if (!$p['valido_fino']) {
            $p['valido_fino'] = (new DateTimeImmutable('today'))->modify('+' . max(1, (int)impostazione('validita_giorni', '30')) . ' days')->format('Y-m-d');
            db()->prepare('UPDATE preventivi SET valido_fino = ? WHERE id = ?')->execute([$p['valido_fino'], $id]);
        }
        $token = bin2hex(random_bytes(32));
        $link = EmailPreventivo::sitoUrl() . '/il-tuo-preventivo.php?t=' . $token;
        $m = EmailPreventivo::preventivo($p, ['nome' => $p['cliente_nome'] ?: $p['nome'], 'nome_proprio' => $p['cliente_nome_proprio'] ?? null, 'tipo' => $p['cliente_tipo'] ?? 'persona'], $link);
        $rispondi = trim((string)impostazione('email_risposta', '')) ?: null;
        $errore = Mailer::invia($to, $m['oggetto'], $m['html'], $m['testo'], $rispondi);
        Mailer::registra($p['cliente_id'] ? (int)$p['cliente_id'] : null, $id, 'preventivo', $to, $m['oggetto'], $errore, $me['email']);
        if ($errore) { flash('errore', 'Email non inviata: ' . $errore); vai('preventivi.php?id=' . $id); }
        db()->prepare("UPDATE preventivi SET token_hash = ?, inviato_il = UTC_TIMESTAMP(), stato = IF(stato IN ('accettato', 'chiuso'), stato, 'inviato') WHERE id = ?")->execute([hash('sha256', $token), $id]);
        if ($p['cliente_id']) {
            $cod = db()->prepare('SELECT codice FROM stati_cliente WHERE id = ?');
            $cod->execute([$p['cliente_stato']]);
            if (in_array($cod->fetchColumn(), ['nuovo', 'richiamato', 'preventivo_inviato', null, false], true)) Clienti::impostaStato((int)$p['cliente_id'], 'preventivo_inviato');
        }
        Auth::log('invia', 'preventivi', $id, $to);
        flash('ok', "Preventivo inviato a $to.");
        vai('preventivi.php?id=' . $id);
    }
}

/* ---------- elenco: filtri ed export ---------- */
$par = []; $w = ['1=1'];
$fs = (string)($_GET['stato'] ?? '');
if (isset(STATI_PREVENTIVO[$fs])) { $w[] = 'p.stato = ?'; $par[] = $fs; }
if (($q = trim((string)($_GET['q'] ?? ''))) !== '') { $w[] = '(p.nome LIKE ? OR p.email LIKE ? OR p.veicolo LIKE ? OR p.comune_nome LIKE ? OR c.nome LIKE ?)'; array_push($par, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
if (in_array($_GET['origine'] ?? '', ['sito', 'backoffice'], true)) { $w[] = 'p.origine = ?'; $par[] = $_GET['origine']; }
$where = implode(' AND ', $w);
$qs = array_filter(['stato' => $fs, 'q' => $q, 'origine' => $_GET['origine'] ?? '']);

if (isset($_GET['esporta'])) {
    $st = db()->prepare("SELECT p.id, p.creato_il, p.origine, p.stato, COALESCE(c.nome, p.nome) AS cliente, COALESCE(c.email, p.email) AS email, COALESCE(c.telefono, p.telefono) AS telefono,
        p.comune_nome, p.eta, p.veicolo, p.garanzie, p.totale, p.totale_definitivo, p.compagnia, p.inviato_il, p.accettato_il FROM preventivi p LEFT JOIN clienti c ON c.id = p.cliente_id WHERE $where ORDER BY p.id DESC");
    $st->execute($par);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="preventivi-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['n', 'data', 'origine', 'stato', 'cliente', 'email', 'telefono', 'comune', 'eta', 'veicolo', 'garanzie', 'stima', 'definitivo', 'compagnia', 'inviato', 'accettato'], ';', '"', '');
    while ($r = $st->fetch()) {
        foreach (['creato_il', 'inviato_il', 'accettato_il'] as $k) $r[$k] = $r[$k] ? data_it($r[$k]) : '';
        foreach (['totale', 'totale_definitivo'] as $k) $r[$k] = str_replace('.', ',', (string)$r[$k]);
        fputcsv($o, $r, ';', '"', '');
    }
    exit;
}

pagina_inizio('Preventivi', 'preventivi');

/* ---------- dettaglio ---------- */
if (isset($_GET['id'])):
    $p = carica((int)$_GET['id']) ?? vai('preventivi.php');
    $d = json_decode((string)$p['dettaglio'], true) ?: [];
    $righe = EmailPreventivo::righe($p);
    $validoDefault = (new DateTimeImmutable('today'))->modify('+' . max(1, (int)impostazione('validita_giorni', '30')) . ' days')->format('Y-m-d');
    $st = db()->prepare('SELECT creato_il, tipo, destinatario, esito, errore, utente FROM email_inviate WHERE preventivo_id = ? ORDER BY id DESC');
    $st->execute([$p['id']]);
    $mail = $st->fetchAll();
    $emailCliente = $p['cliente_email'] ?: $p['email']; ?>
  <p class="crumbs"><a href="preventivi.php">← Preventivi</a><?php if ($p['cliente_id']): ?> · <a href="clienti.php?id=<?= (int)$p['cliente_id'] ?>">Scheda di <?= e($p['cliente_nome']) ?></a><?php endif; ?></p>
  <h1>Preventivo n. <?= (int)$p['id'] ?> <?= stato_preventivo($p['stato']) ?></h1>
  <p class="muted"><?= $p['origine'] === 'sito' ? 'Richiesto dal sito' : 'Creato in agenzia' . ($p['creato_da'] ? ' da ' . e($p['creato_da']) : '') ?> il <?= e(data_it($p['creato_il'])) ?>
    <?= $p['inviato_il'] ? ' · inviato il ' . e(data_it($p['inviato_il'])) : '' ?><?= $p['accettato_il'] ? ' · <b>accettato dal cliente il ' . e(data_it($p['accettato_il'])) . '</b>' : '' ?></p>
  <div class="cols cols--wide">
    <section class="box">
      <h2><?= $p['capitale'] !== null ? 'Cliente e polizza' : 'Cliente e veicolo' ?></h2>
      <dl class="dl">
        <dt>Cliente</dt><dd><?= $p['cliente_id'] ? '<a href="clienti.php?id=' . (int)$p['cliente_id'] . '">' . e($p['cliente_nome']) . '</a>' : e($p['nome']) ?></dd>
        <dt>Email</dt><dd><?= $emailCliente ? '<a href="mailto:' . e($emailCliente) . '">' . e($emailCliente) . '</a>' : '<span class="muted">manca</span>' ?></dd>
        <dt>Telefono</dt><dd><?= e(($p['cliente_tel'] ?: $p['telefono']) ?: '–') ?></dd>
        <?php if ($p['capitale'] !== null): $dq = json_decode((string)$p['dettaglio'], true) ?: []; ?>
        <dt>Polizza</dt><dd><?= e($dq['tipo_polizza'] ?? $p['veicolo']) ?></dd>
        <dt>Capitale</dt><dd><?= euro($p['capitale']) ?></dd>
        <?php foreach ($dq['risposte'] ?? [] as $x): ?><dt><?= e($x['domanda']) ?></dt><dd><?= e($x['risposta']) ?></dd><?php endforeach; ?>
        <?php else: ?>
        <dt>Residenza</dt><dd><?= e($p['comune_nome']) ?></dd>
        <dt>Contraente</dt><dd><?= $p['data_nascita'] ? e(date('d/m/Y', strtotime($p['data_nascita']))) . ' · ' . (int)$p['eta'] . ' anni' : '–' ?></dd>
        <dt>Veicolo</dt><dd><?= e($p['veicolo']) ?></dd>
        <?php endif; ?>
      </dl>
      <?php if ($p['note']): ?><h3>Note del cliente</h3><p class="pre"><?= e($p['note']) ?></p><?php endif; ?>
      <h3>Stima automatica</h3>
      <table class="table table--small"><tbody>
      <?php foreach ($d['righe'] ?? [] as $r): ?><tr><td><?= e($r['nome']) ?><br><small class="muted"><?= e($r['spiegazione'] ?? '') ?></small></td><td class="right"><?= $r['importo'] === null ? 'su richiesta' : euro($r['importo']) ?></td></tr><?php endforeach; ?>
      <?php foreach ($d['sconti'] ?? [] as $r): ?><tr><td><?= e($r['nome']) ?></td><td class="right"><?= euro($r['importo']) ?></td></tr><?php endforeach; ?>
      <tr class="tot"><td>Totale stimato<?= !empty($d['prodotto']) ? '<br><small class="muted">' . e($d['compagnia'] . ' – ' . $d['prodotto']) . (($d['rate'] ?? 1) > 1 ? ' · ' . (int)$d['rate'] . ' rate da ' . euro($d['rata']) : '') . (($d['durata'] ?? 1) > 1 ? ' · ' . (int)$d['durata'] . ' anni' : '') . '</small>' : '' ?></td><td class="right"><?= euro($p['totale']) ?></td></tr>
      </tbody></table>
      <?php tabella_confronto($d, (int)$p['id'], $p['prodotto_id'] !== null ? (int)$p['prodotto_id'] : null); ?>
      <h3>Email</h3>
      <?php if (!$mail): ?><p class="muted">Nessuna email per questo preventivo.</p><?php else: ?>
      <ul class="log"><?php foreach ($mail as $m): ?><li><span class="muted"><?= e(data_it($m['creato_il'])) ?></span> <?= e(['preventivo' => 'Preventivo al cliente', 'avviso_agenzia' => 'Avviso all\'agenzia', 'avviso_accettazione' => 'Avviso di accettazione'][$m['tipo']] ?? $m['tipo']) ?> → <?= e($m['destinatario']) ?> <?= $m['esito'] === 'inviata' ? '<span class="tag tag--ok">inviata</span>' : '<span class="tag tag--nuovo">errore</span> <small class="err">' . e((string)$m['errore']) . '</small>' ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
    <section class="box">
      <h2>Preventivo per il cliente</h2>
      <form method="post" class="form" id="defin">
        <?= Auth::campoCsrf() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <table class="table table--edit"><thead><tr><th>Voce</th><th class="right">Importo annuo (€)</th><th></th></tr></thead><tbody>
        <?php foreach ($righe as $i => $r): ?>
          <tr><td><input name="riga_nome[<?= $i ?>]" value="<?= e($r['nome']) ?>" aria-label="Voce"></td>
          <td><input name="riga_importo[<?= $i ?>]" value="<?= $r['importo'] === null ? '' : e(number_format((float)$r['importo'], 2, ',', '')) ?>" inputmode="decimal" class="right" aria-label="Importo"><?php if ($i === 0): ?><small class="hint">Vuoto = su richiesta</small><?php endif; ?></td>
          <td><?php if ($i > 0): ?><label class="check" title="Togli questa voce"><input type="checkbox" name="riga_togli[<?= $i ?>]" value="1"> togli</label><?php endif; ?></td></tr>
        <?php endforeach; ?>
          <tr><td><input name="nuova_nome" aria-label="Nuova voce" list="garanzie-list" autocomplete="off" data-garanzie><datalist id="garanzie-list"></datalist><small class="hint">Scrivi le prime lettere e scegli una garanzia della compagnia, oppure una voce libera</small></td><td><input name="nuova_importo" inputmode="decimal" class="right" aria-label="Importo nuova voce"><small class="hint">Importo in €</small></td><td></td></tr>
        </tbody></table>
        <script type="application/json" id="garanzie-dati"><?= json_encode(garanzie_per_preventivo($p), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
        <p class="muted">Totale attuale: <b><?= euro(EmailPreventivo::totale($p)) ?></b> · si ricalcola al salvataggio.</p>
        <div class="form--grid2">
          <label>Compagnia<input name="compagnia" maxlength="80" value="<?= e((string)$p['compagnia']) ?>"><small class="hint">Es. Allianz</small></label>
          <label>Stato<select name="stato"><?php foreach (STATI_PREVENTIVO as $k => $l): ?><option value="<?= $k ?>"<?= $p['stato'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
          <label>Decorrenza<input type="date" name="decorrenza" value="<?= e((string)$p['decorrenza']) ?>"></label>
          <label>Valido fino al<input type="date" name="valido_fino" value="<?= e((string)($p['valido_fino'] ?: $validoDefault)) ?>"></label>
        </div>
        <label>Messaggio al cliente<textarea name="messaggio" rows="4"><?= e((string)$p['messaggio']) ?></textarea><small>Vuoto = messaggio standard (lo cambi in Impostazioni).</small></label>
        <div class="actions actions--wrap">
          <button class="btn btn--ghost" name="azione" value="salva">Salva</button>
          <a class="btn btn--ghost" href="anteprima.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">Anteprima email ↗</a>
          <?php if ($p['cliente_id'] && in_array($p['stato'], ['accettato', 'chiuso'], true)): ?><a class="btn btn--ghost" href="polizze.php?nuovo=1&amp;preventivo=<?= (int)$p['id'] ?>">Registra la polizza stipulata</a><?php endif; ?>
          <button class="btn" name="azione" value="invia" data-conferma-btn="Salvare e inviare il preventivo a <?= e((string)$emailCliente) ?>?"<?= $emailCliente ? '' : ' disabled' ?>><?= $p['inviato_il'] ? 'Salva e invia di nuovo' : 'Salva e invia al cliente' ?></button>
        </div>
        <?php if (!Mailer::configurato()): ?><p class="flash flash--info">La casella di posta per l'invio non è ancora collegata: le email partono dalla funzione di base dell'hosting e potrebbero finire nello spam. Vedi <a href="impostazioni.php">Impostazioni</a>.</p><?php endif; ?>
        <?php if ($p['inviato_il']): ?><p class="muted">Un nuovo invio sostituisce il link del precedente: vale solo l'ultima email.</p><?php endif; ?>
      </form>
    </section>
  </div>
  <form method="post" class="danger" data-conferma="Eliminare definitivamente questo preventivo?">
    <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <button class="btn btn--danger">Elimina il preventivo</button>
  </form>
<?php
/* ---------- elenco ---------- */
else:
    $per = 50; $pag = max(1, (int)($_GET['p'] ?? 1));
    $st = db()->prepare("SELECT COUNT(*) FROM preventivi p LEFT JOIN clienti c ON c.id = p.cliente_id WHERE $where"); $st->execute($par); $tot = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT p.id, p.creato_il, p.origine, COALESCE(c.nome, p.nome) AS cliente, p.cliente_id, COALESCE(c.email, p.email) AS email, p.veicolo, p.comune_nome,
        COALESCE(p.totale_definitivo, p.totale) AS importo, p.stato FROM preventivi p LEFT JOIN clienti c ON c.id = p.cliente_id WHERE $where ORDER BY p.id DESC LIMIT $per OFFSET " . (($pag - 1) * $per));
    $st->execute($par);
    $conta = db()->query('SELECT stato, COUNT(*) FROM preventivi GROUP BY stato')->fetchAll(PDO::FETCH_KEY_PAIR); ?>
  <div class="head"><h1>Preventivi <span class="muted">(<?= $tot ?>)</span></h1>
    <div class="head__actions"><a class="btn btn--ghost" href="?<?= e(http_build_query($qs + ['esporta' => 1])) ?>">Esporta CSV</a><a class="btn" href="clienti.php">+ Nuovo (scegli il cliente)</a></div></div>
  <nav class="chips" aria-label="Filtra per stato">
    <a href="preventivi.php"<?= $fs === '' ? ' aria-current="page"' : '' ?>>Tutti</a>
    <?php foreach (STATI_PREVENTIVO as $k => $l): ?><a href="?stato=<?= $k ?>"<?= $fs === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?> <span><?= (int)($conta[$k] ?? 0) ?></span></a><?php endforeach; ?>
  </nav>
  <form method="get" class="filters">
    <?php if ($fs !== ''): ?><input type="hidden" name="stato" value="<?= e($fs) ?>"><?php endif; ?>
    <span class="campo"><input type="search" name="q" value="<?= e($q) ?>" aria-label="Cerca"><small class="hint">Cliente, email, veicolo o comune</small></span>
    <select name="origine" aria-label="Origine"><option value="">Sito e agenzia</option><option value="sito"<?= ($_GET['origine'] ?? '') === 'sito' ? ' selected' : '' ?>>Solo dal sito</option><option value="backoffice"<?= ($_GET['origine'] ?? '') === 'backoffice' ? ' selected' : '' ?>>Solo in agenzia</option></select>
    <button class="btn btn--ghost">Filtra</button>
  </form>
  <div class="tablewrap"><table class="table">
    <thead><tr><th>N.</th><th>Data</th><th>Cliente</th><th>Veicolo</th><th>Comune</th><th class="right">Importo</th><th>Stato</th></tr></thead>
    <tbody>
    <?php foreach ($st as $p): ?>
      <tr><td><a href="?id=<?= (int)$p['id'] ?>"><?= (int)$p['id'] ?></a></td><td><?= e(data_it($p['creato_il'])) ?><br><small class="muted"><?= $p['origine'] === 'sito' ? 'dal sito' : 'in agenzia' ?></small></td>
      <td><a href="?id=<?= (int)$p['id'] ?>"><?= e($p['cliente']) ?></a><br><small class="muted"><?= e((string)$p['email']) ?></small></td>
      <td><?= e($p['veicolo']) ?></td><td><?= e($p['comune_nome']) ?></td><td class="right"><?= euro($p['importo']) ?></td><td><?= stato_preventivo($p['stato']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$tot): ?><tr><td colspan="7" class="muted">Nessun preventivo.</td></tr><?php endif; ?>
    </tbody></table></div>
  <?= paginazione($tot, $pag, $per, $qs) ?>
<?php endif;
pagina_fine();
