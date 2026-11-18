<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
$u = Auth::richiedi();

$c = fn(string $q) => (int)db()->query($q)->fetchColumn();
$nuovi = $c("SELECT COUNT(*) FROM preventivi WHERE stato = 'nuovo'");
$clientiNuovi = $c("SELECT COUNT(*) FROM clienti c JOIN stati_cliente s ON s.id = c.stato_id WHERE s.codice = 'nuovo'");
$accettati = $c("SELECT COUNT(*) FROM preventivi WHERE stato = 'accettato'");
$mese = $c('SELECT COUNT(*) FROM preventivi WHERE creato_il >= UTC_TIMESTAMP() - INTERVAL 30 DAY');
$rc = db()->query('SELECT valore FROM garanzie WHERE principale = 1')->fetchColumn();
$ultimi = db()->query('SELECT p.id, p.creato_il, COALESCE(c.nome, p.nome) AS nome, p.veicolo, p.comune_nome, COALESCE(p.totale_definitivo, p.totale) AS totale, p.stato FROM preventivi p LEFT JOIN clienti c ON c.id = p.cliente_id ORDER BY p.id DESC LIMIT 8')->fetchAll();
$log = db()->query('SELECT creato_il, utente, azione, tabella, record_id FROM log_modifiche ORDER BY id DESC LIMIT 8')->fetchAll();
$attivo = impostazione('preventivatore_attivo', '1') === '1';
pagina_inizio('Riepilogo', 'index');
?>
<h1>Ciao <?= e(explode(' ', $u['nome'])[0]) ?></h1>
<?php if (!$attivo): ?><p class="flash flash--errore">Il preventivatore pubblico è <strong>sospeso</strong>. Riattivalo da <a href="impostazioni.php">Impostazioni</a>.</p><?php endif; ?>
<?php if (trim((string)impostazione('email_notifiche', '')) === ''): ?><p class="flash flash--info">Nessuna email riceve gli avvisi delle nuove richieste: impostala in <a href="impostazioni.php">Impostazioni</a>.</p><?php endif; ?>
<?php if (!Mailer::configurato()): ?><p class="flash flash--info">La casella di posta per inviare i preventivi non è ancora collegata: vedi <a href="impostazioni.php">Impostazioni</a>.</p><?php endif; ?>
<div class="cards">
  <a class="card" href="preventivi.php?stato=nuovo"><span class="card__n"><?= $nuovi ?></span><span>preventivi da lavorare</span></a>
  <a class="card" href="clienti.php?stato=<?= Clienti::stato('nuovo') ?>"><span class="card__n"><?= $clientiNuovi ?></span><span>nuovi contatti da richiamare</span></a>
  <a class="card" href="preventivi.php?stato=accettato"><span class="card__n"><?= $accettati ?></span><span>preventivi accettati</span></a>
  <a class="card" href="prova.php"><span class="card__n">Prova</span><span>calcola una stima di prova</span></a>
</div>
<div class="cols">
  <section>
    <h2>Ultimi preventivi</h2>
    <?php if (!$ultimi): ?><p class="muted">Ancora nessuna richiesta dal sito.</p><?php else: ?>
    <table class="table"><tbody>
    <?php foreach ($ultimi as $p): ?>
      <tr><td><a href="preventivi.php?id=<?= (int)$p['id'] ?>"><?= e($p['nome']) ?></a><br><small class="muted"><?= e($p['veicolo']) ?> · <?= e($p['comune_nome']) ?></small></td>
      <td class="right"><?= euro($p['totale']) ?><br><?= stato_preventivo($p['stato']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table><?php endif; ?>
  </section>
  <section>
    <h2>Ultime modifiche</h2>
    <?php if (!$log): ?><p class="muted">Nessuna modifica registrata.</p><?php else: ?>
    <ul class="log"><?php foreach ($log as $l): ?><li><span class="muted"><?= e(data_it($l['creato_il'])) ?></span> <?= e($l['utente'] ?? '–') ?>: <?= e($l['azione']) ?> in <?= e($l['tabella']) ?><?= $l['record_id'] ? ' n. ' . (int)$l['record_id'] : '' ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </section>
</div>
<?php pagina_fine();
