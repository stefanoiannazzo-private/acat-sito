<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
$u = Auth::richiedi();

$c = fn(string $q) => (int)db()->query($q)->fetchColumn();
$nuovi = $c("SELECT COUNT(*) FROM preventivi WHERE stato = 'nuovo'");
$mese = $c('SELECT COUNT(*) FROM preventivi WHERE creato_il >= UTC_TIMESTAMP() - INTERVAL 30 DAY');
$rc = db()->query('SELECT valore FROM garanzie WHERE principale = 1')->fetchColumn();
$ultimi = db()->query('SELECT id, creato_il, nome, veicolo, comune_nome, totale, stato FROM preventivi ORDER BY id DESC LIMIT 8')->fetchAll();
$log = db()->query('SELECT creato_il, utente, azione, tabella, record_id FROM log_modifiche ORDER BY id DESC LIMIT 8')->fetchAll();
$attivo = impostazione('preventivatore_attivo', '1') === '1';
pagina_inizio('Riepilogo', 'index');
?>
<h1>Ciao <?= e(explode(' ', $u['nome'])[0]) ?></h1>
<?php if (!$attivo): ?><p class="flash flash--errore">Il preventivatore pubblico è <strong>sospeso</strong>. Riattivalo da <a href="impostazioni.php">Impostazioni</a>.</p><?php endif; ?>
<?php if (trim((string)impostazione('email_notifiche', '')) === ''): ?><p class="flash flash--info">Nessuna email riceve le richieste di preventivo: impostala in <a href="impostazioni.php">Impostazioni</a>.</p><?php endif; ?>
<div class="cards">
  <a class="card" href="preventivi.php?stato=nuovo"><span class="card__n"><?= $nuovi ?></span><span>richieste da gestire</span></a>
  <a class="card" href="preventivi.php"><span class="card__n"><?= $mese ?></span><span>richieste negli ultimi 30 giorni</span></a>
  <a class="card" href="tabella.php?t=garanzie"><span class="card__n"><?= euro($rc) ?></span><span>premio base dell'RC</span></a>
  <a class="card" href="prova.php"><span class="card__n">Prova</span><span>calcola una stima di prova</span></a>
</div>
<div class="cols">
  <section>
    <h2>Ultime richieste</h2>
    <?php if (!$ultimi): ?><p class="muted">Ancora nessuna richiesta dal sito.</p><?php else: ?>
    <table class="table"><tbody>
    <?php foreach ($ultimi as $p): ?>
      <tr><td><a href="preventivi.php?id=<?= (int)$p['id'] ?>"><?= e($p['nome']) ?></a><br><small class="muted"><?= e($p['veicolo']) ?> · <?= e($p['comune_nome']) ?></small></td>
      <td class="right"><?= euro($p['totale']) ?><br><span class="tag tag--<?= e($p['stato']) ?>"><?= e($p['stato']) ?></span></td></tr>
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
