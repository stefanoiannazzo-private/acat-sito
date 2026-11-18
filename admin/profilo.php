<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
$u = Auth::richiedi();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('SELECT password_hash FROM utenti_admin WHERE id = ?');
    $st->execute([$u['id']]);
    $p = (string)($_POST['nuova'] ?? '');
    if (!password_verify((string)($_POST['attuale'] ?? ''), (string)$st->fetchColumn())) flash('errore', 'La password attuale non è corretta.');
    elseif ($e = Auth::validaPassword($p)) flash('errore', $e);
    elseif ($p !== (string)($_POST['nuova2'] ?? '')) flash('errore', 'Le due password non coincidono.');
    else {
        db()->prepare('UPDATE utenti_admin SET password_hash = ? WHERE id = ?')->execute([password_hash($p, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
        Auth::log('password', 'utenti_admin', (int)$u['id']);
        flash('ok', 'Password cambiata.');
    }
    vai('profilo.php');
}
pagina_inizio('Il tuo profilo');
?>
<h1>Il tuo profilo</h1>
<p><?= e($u['nome']) ?> · <?= e($u['email']) ?> · <?= e($u['ruolo']) ?></p>
<h2>Cambia password</h2>
<form method="post" class="form form--narrow">
  <?= Auth::campoCsrf() ?>
  <label>Password attuale<input type="password" name="attuale" required autocomplete="current-password"></label>
  <label>Nuova password<input type="password" name="nuova" required minlength="10" autocomplete="new-password"><small>Almeno 10 caratteri, con lettere e numeri.</small></label>
  <label>Ripeti la nuova password<input type="password" name="nuova2" required minlength="10" autocomplete="new-password"></label>
  <button class="btn">Cambia password</button>
</form>
<?php pagina_fine();
