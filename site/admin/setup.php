<?php
/* Primo accesso: crea il primo amministratore. Funziona solo finché non esiste nessun utente,
   e chiede la password del database (che conosce solo chi gestisce l'hosting). */
declare(strict_types=1);
require __DIR__ . '/_init.php';

if ((int)db()->query('SELECT COUNT(*) FROM utenti_admin')->fetchColumn() > 0) vai('login.php');

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::bloccato()) $err[] = 'Troppi tentativi non riusciti. Riprova tra 15 minuti.';
    elseif (!hash_equals((string)config()['db']['pass'], (string)($_POST['chiave'] ?? ''))) {
        db()->prepare('INSERT INTO tentativi_accesso (ip_hash, email, creato_il) VALUES (?, ?, UTC_TIMESTAMP())')->execute([client_ip_hash(), 'setup']);
        $err[] = 'La password del database non è corretta.';
    }
    $nome = trim((string)($_POST['nome'] ?? ''));
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $p = (string)($_POST['password'] ?? '');
    if ($nome === '') $err[] = 'Scrivi il tuo nome.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Controlla l\'email.';
    if ($e = Auth::validaPassword($p)) $err[] = $e;
    if ($p !== (string)($_POST['password2'] ?? '')) $err[] = 'Le due password non coincidono.';
    if (!$err) {
        db()->prepare('INSERT INTO utenti_admin (email, nome, password_hash, ruolo, creato_il) VALUES (?, ?, ?, \'admin\', UTC_TIMESTAMP())')
            ->execute([$email, mb_substr($nome, 0, 120), password_hash($p, PASSWORD_DEFAULT)]);
        Auth::accedi($email, $p);
        Auth::log('crea', 'utenti_admin', (int)db()->lastInsertId(), 'Primo amministratore');
        flash('ok', 'Account creato. Benvenuto nel pannello.');
        vai('index.php');
    }
}
pagina_inizio('Primo accesso');
?>
<div class="auth">
  <img src="../logo-acat.svg" alt="ACAT Servizi" width="152" height="32">
  <h1>Primo accesso</h1>
  <p>Crea l'account dell'amministratore. Per sicurezza serve la <strong>password del database</strong> che hai impostato su Aruba (il secret <code>DB_PASS</code> di GitHub).</p>
  <?php foreach ($err as $m): ?><p class="flash flash--errore" role="alert"><?= e($m) ?></p><?php endforeach; ?>
  <form method="post" class="form" autocomplete="off">
    <?= Auth::campoCsrf() ?>
    <label>Password del database<input type="password" name="chiave" required autocomplete="off"></label>
    <label>Il tuo nome<input name="nome" required value="<?= e((string)($_POST['nome'] ?? '')) ?>"></label>
    <label>La tua email<input type="email" name="email" required autocomplete="username" value="<?= e((string)($_POST['email'] ?? '')) ?>"></label>
    <label>Scegli una password<input type="password" name="password" required minlength="10" autocomplete="new-password"><small>Almeno 10 caratteri, con lettere e numeri.</small></label>
    <label>Ripeti la password<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
    <button class="btn">Crea l'account</button>
  </form>
</div>
<?php pagina_fine();
