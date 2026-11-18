<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
$me = Auth::richiedi(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $az = $_POST['azione'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($az === 'crea') {
        $nome = trim((string)($_POST['nome'] ?? '')); $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $ruolo = ($_POST['ruolo'] ?? '') === 'admin' ? 'admin' : 'operatore'; $p = (string)($_POST['password'] ?? '');
        $e = $nome === '' ? 'Scrivi il nome.' : (!filter_var($email, FILTER_VALIDATE_EMAIL) ? 'Email non valida.' : Auth::validaPassword($p));
        if ($e) flash('errore', $e);
        else {
            try {
                db()->prepare('INSERT INTO utenti_admin (email, nome, password_hash, ruolo, creato_il) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())')->execute([$email, $nome, password_hash($p, PASSWORD_DEFAULT), $ruolo]);
                Auth::log('crea', 'utenti_admin', (int)db()->lastInsertId(), $email);
                flash('ok', "Utente creato. Comunica a $email la password in modo sicuro e chiedigli di cambiarla dal suo profilo.");
            } catch (PDOException $ex) { if ($ex->getCode() !== '23000') throw $ex; flash('errore', 'Esiste già un utente con questa email.'); }
        }
    } elseif ($id === (int)$me['id']) {
        flash('errore', 'Non puoi disattivare o cambiare il ruolo del tuo account.');
    } elseif ($az === 'attivo') {
        db()->prepare('UPDATE utenti_admin SET attivo = 1 - attivo WHERE id = ?')->execute([$id]);
        Auth::log('attivo', 'utenti_admin', $id);
        flash('ok', 'Stato dell\'utente aggiornato.');
    } elseif ($az === 'ruolo') {
        db()->prepare("UPDATE utenti_admin SET ruolo = IF(ruolo = 'admin', 'operatore', 'admin') WHERE id = ?")->execute([$id]);
        Auth::log('ruolo', 'utenti_admin', $id);
        flash('ok', 'Ruolo aggiornato.');
    }
    vai('utenti.php');
}
$utenti = db()->query('SELECT id, nome, email, ruolo, attivo, ultimo_accesso FROM utenti_admin ORDER BY nome')->fetchAll();
pagina_inizio('Utenti', 'utenti');
?>
<h1>Utenti del pannello</h1>
<p class="intro">Gli <strong>amministratori</strong> gestiscono anche gli utenti; gli <strong>operatori</strong> tutto il resto (tariffe, elenchi, richieste).</p>
<div class="tablewrap"><table class="table">
<thead><tr><th>Nome</th><th>Email</th><th>Ruolo</th><th>Ultimo accesso</th><th>Stato</th><th></th></tr></thead><tbody>
<?php foreach ($utenti as $u): ?>
  <tr<?= $u['attivo'] ? '' : ' class="off"' ?>><td><?= e($u['nome']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['ruolo']) ?></td><td><?= e(data_it($u['ultimo_accesso'])) ?></td>
  <td><?= $u['attivo'] ? '<span class="tag tag--ok">Attivo</span>' : '<span class="tag">Disattivato</span>' ?></td>
  <td class="right"><?php if ((int)$u['id'] !== (int)$me['id']): ?>
    <form method="post" class="inline"><?= Auth::campoCsrf() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="linkbtn" name="azione" value="ruolo">Cambia ruolo</button> · <button class="linkbtn" name="azione" value="attivo"><?= $u['attivo'] ? 'Disattiva' : 'Riattiva' ?></button></form>
  <?php else: ?><span class="muted">sei tu</span><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<h2>Nuovo utente</h2>
<form method="post" class="form form--narrow" autocomplete="off">
  <?= Auth::campoCsrf() ?><input type="hidden" name="azione" value="crea">
  <label>Nome<input name="nome" required></label>
  <label>Email<input type="email" name="email" required></label>
  <label>Ruolo<select name="ruolo"><option value="operatore">Operatore</option><option value="admin">Amministratore</option></select></label>
  <label>Password iniziale<input type="password" name="password" required minlength="10" autocomplete="new-password"><small>Almeno 10 caratteri, con lettere e numeri.</small></label>
  <button class="btn">Crea utente</button>
</form>
<?php pagina_fine();
