<?php
/* Avvio comune di pannello admin e API del preventivatore. */
declare(strict_types=1);

const APP_DIR = __DIR__;
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Rome');

spl_autoload_register(function (string $c): void {
    $f = APP_DIR . '/lib/' . $c . '.php';
    if (is_file($f)) require $f;
});
require APP_DIR . '/lib/helpers.php';
if (is_file(APP_DIR . '/vendor/autoload.php')) require APP_DIR . '/vendor/autoload.php';

function config(): array {
    static $c = null;
    if ($c === null) {
        $f = APP_DIR . '/config.php';
        if (!is_file($f)) {
            http_response_code(503);
            exit('Configurazione mancante: il file app/config.php viene creato dalla pubblicazione automatica.');
        }
        $c = require $f;
    }
    return $c;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $d = config()['db'];
        $pdo = new PDO(
            'mysql:host=' . $d['host'] . ';dbname=' . $d['name'] . ';charset=utf8mb4',
            $d['user'], $d['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}
