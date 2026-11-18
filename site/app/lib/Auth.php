<?php
declare(strict_types=1);

/** Sessione, accesso, protezione CSRF e blocco dopo troppi tentativi per il pannello admin. */
final class Auth
{
    private const MAX_TENTATIVI = 5;      // in 15 minuti, per indirizzo IP
    private const INATTIVITA = 7200;      // secondi prima di chiedere di nuovo l'accesso

    public static function avvia(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        session_name('acat_admin');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/admin/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
        ini_set('session.use_strict_mode', '1');
        session_start();
        if (isset($_SESSION['uid'], $_SESSION['visto']) && time() - $_SESSION['visto'] > self::INATTIVITA) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['avviso'] = 'Sessione scaduta per inattività: accedi di nuovo.';
        }
        if (isset($_SESSION['uid'])) $_SESSION['visto'] = time();
    }

    public static function utente(): ?array
    {
        static $u = false;
        if ($u !== false) return $u;
        if (empty($_SESSION['uid'])) return $u = null;
        $st = db()->prepare('SELECT id, email, nome, ruolo FROM utenti_admin WHERE id = ? AND attivo = 1');
        $st->execute([$_SESSION['uid']]);
        return $u = ($st->fetch() ?: null);
    }

    public static function richiedi(bool $soloAdmin = false): array
    {
        $u = self::utente();
        if (!$u) {
            header('Location: login.php');
            exit;
        }
        if ($soloAdmin && $u['ruolo'] !== 'admin') {
            http_response_code(403);
            exit('Pagina riservata agli amministratori.');
        }
        return $u;
    }

    public static function bloccato(): bool
    {
        $st = db()->prepare('SELECT COUNT(*) FROM tentativi_accesso WHERE ip_hash = ? AND creato_il > UTC_TIMESTAMP() - INTERVAL 15 MINUTE');
        $st->execute([client_ip_hash()]);
        return (int)$st->fetchColumn() >= self::MAX_TENTATIVI;
    }

    /** @return string|null messaggio di errore, null se l'accesso è riuscito */
    public static function accedi(string $email, string $password): ?string
    {
        if (self::bloccato()) return 'Troppi tentativi non riusciti. Riprova tra 15 minuti.';
        $st = db()->prepare('SELECT id, password_hash FROM utenti_admin WHERE email = ? AND attivo = 1');
        $st->execute([mb_strtolower(trim($email))]);
        $r = $st->fetch();
        // stesso tempo di risposta anche se l'utente non esiste
        $hash = $r['password_hash'] ?? '$2y$10$WyHiYp8JMTu5M/rn2yl0I.HO2V0rOvxNfftYzWbaNbEK4eXD.e7/6';
        if (!password_verify($password, $hash) || !$r) {
            db()->prepare('INSERT INTO tentativi_accesso (ip_hash, email, creato_il) VALUES (?, ?, UTC_TIMESTAMP())')->execute([client_ip_hash(), mb_substr($email, 0, 190)]);
            return 'Email o password non corrette.';
        }
        if (password_needs_rehash($r['password_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE utenti_admin SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $r['id']]);
        }
        session_regenerate_id(true);
        $_SESSION = ['uid' => (int)$r['id'], 'visto' => time(), 'csrf' => bin2hex(random_bytes(32))];
        db()->prepare('UPDATE utenti_admin SET ultimo_accesso = UTC_TIMESTAMP() WHERE id = ?')->execute([$r['id']]);
        db()->prepare('DELETE FROM tentativi_accesso WHERE ip_hash = ?')->execute([client_ip_hash()]);
        return null;
    }

    public static function esci(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict']);
        }
        session_destroy();
    }

    public static function csrf(): string
    {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }

    public static function campoCsrf(): string
    {
        return '<input type="hidden" name="csrf" value="' . e(self::csrf()) . '">';
    }

    public static function verificaCsrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
        if (!hash_equals(self::csrf(), (string)($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            exit('Modulo scaduto: torna indietro, ricarica la pagina e riprova.');
        }
    }

    public static function validaPassword(string $p): ?string
    {
        if (mb_strlen($p) < 10) return 'La password deve avere almeno 10 caratteri.';
        if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/[0-9]/', $p)) return 'La password deve contenere lettere e numeri.';
        return null;
    }

    public static function log(string $azione, string $tabella, ?int $id, array|string|null $dettagli = null): void
    {
        $u = self::utente();
        db()->prepare('INSERT INTO log_modifiche (utente_id, utente, azione, tabella, record_id, dettagli, creato_il) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())')
            ->execute([$u['id'] ?? null, $u['email'] ?? null, $azione, $tabella, $id, is_array($dettagli) ? json_encode($dettagli, JSON_UNESCAPED_UNICODE) : $dettagli]);
    }
}
