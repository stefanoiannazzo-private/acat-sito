<?php
declare(strict_types=1);

/** Limite di richieste per indirizzo IP (anonimizzato), a finestre di un minuto. */
final class Limiti
{
    public static function consenti(string $chiave, int $massimo): bool
    {
        $db = db();
        $finestra = intdiv(time(), 60);
        $st = $db->prepare('INSERT INTO limiti_api (ip_hash, finestra, conteggio) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE conteggio = conteggio + 1');
        $st->execute([hash('sha256', $chiave . '|' . client_ip_hash()), $finestra]);
        $st = $db->prepare('SELECT conteggio FROM limiti_api WHERE ip_hash = ? AND finestra = ?');
        $st->execute([hash('sha256', $chiave . '|' . client_ip_hash()), $finestra]);
        if (random_int(1, 50) === 1) $db->prepare('DELETE FROM limiti_api WHERE finestra < ?')->execute([$finestra - 60]);
        return (int)$st->fetchColumn() <= $massimo;
    }
}
