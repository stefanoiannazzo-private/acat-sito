<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

if ((int)db()->query('SELECT COUNT(*) FROM utenti_admin')->fetchColumn() === 0) vai('setup.php');
if (Auth::utente()) vai('index.php');

$errore = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errore = Auth::accedi((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($errore === null) vai('index.php');
}
pagina_inizio('Accedi');
?>
<div class="auth">
  <img src="../logo-acat.svg" alt="ACAT Servizi" width="152" height="32">
  <h1>Pannello del preventivatore</h1>
  <?php if (!empty($_SESSION['avviso'])): ?><p class="flash flash--info"><?= e($_SESSION['avviso']) ?></p><?php unset($_SESSION['avviso']); endif; ?>
  <?php if ($errore): ?><p class="flash flash--errore" role="alert"><?= e($errore) ?></p><?php endif; ?>
  <form method="post" class="form">
    <?= Auth::campoCsrf() ?>
    <label>Email<input type="email" name="email" required autocomplete="username" value="<?= e((string)($_POST['email'] ?? '')) ?>"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn">Accedi</button>
  </form>
</div>
<?php pagina_fine();
