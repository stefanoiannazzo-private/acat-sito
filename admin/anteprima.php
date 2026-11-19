<?php
/* Anteprima dell'email del preventivo, esattamente come la riceverà il cliente (il pulsante non è attivo). */
declare(strict_types=1);
require __DIR__ . '/_init.php';
Auth::richiedi();
$st = db()->prepare('SELECT p.*, c.nome AS cliente_nome, c.nome_proprio AS cliente_nome_proprio, c.tipo AS cliente_tipo FROM preventivi p LEFT JOIN clienti c ON c.id = p.cliente_id WHERE p.id = ?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$p = $st->fetch();
if (!$p) vai('preventivi.php');
if (!$p['valido_fino']) $p['valido_fino'] = (new DateTimeImmutable('today'))->modify('+' . max(1, (int)impostazione('validita_giorni', '30')) . ' days')->format('Y-m-d');
$m = EmailPreventivo::preventivo($p, ['nome' => $p['cliente_nome'] ?: $p['nome'], 'nome_proprio' => $p['cliente_nome_proprio'] ?? null, 'tipo' => $p['cliente_tipo'] ?? 'persona'], '#anteprima');
header("Content-Security-Policy: default-src 'none'; img-src 'self' https: data:; style-src 'unsafe-inline'; font-src 'self'; frame-ancestors 'none'");
echo str_replace('<body style="', '<body style="padding-top:44px;', str_replace('</body>',
    '<div style="position:fixed;top:0;left:0;right:0;padding:10px 16px;background:#fff3d6;color:#8a5a00;font:14px Helvetica,Arial,sans-serif;text-align:center;">Anteprima · Oggetto: <b>' . e($m['oggetto']) . '</b> · Salva il preventivo per vedere le ultime modifiche</div></body>', $m['html']));
