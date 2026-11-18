<?php
declare(strict_types=1);

/** Anagrafica: trova o crea il cliente di una richiesta, gestisce gli stati automatici. */
final class Clienti
{
    public static function stato(string $codice): int
    {
        $st = db()->prepare('SELECT id FROM stati_cliente WHERE codice = ?');
        $st->execute([$codice]);
        return (int)$st->fetchColumn();
    }

    public static function stati(bool $soloAttivi = false): array
    {
        return db()->query('SELECT id, codice, nome, colore, sistema, attivo FROM stati_cliente' . ($soloAttivi ? ' WHERE attivo = 1' : '') . ' ORDER BY ordine, nome')->fetchAll();
    }

    /**
     * Cliente con la stessa email (senza distinguere maiuscole) oppure nuovo cliente.
     * Su un cliente esistente completa solo i dati mancanti, non sovrascrive quelli già presenti.
     */
    public static function trovaOCrea(array $d, string $origine = 'sito'): int
    {
        $email = mb_strtolower(trim((string)($d['email'] ?? '')));
        if ($email !== '') {
            $st = db()->prepare('SELECT id FROM clienti WHERE LOWER(email) = ? ORDER BY id LIMIT 1');
            $st->execute([$email]);
            if ($id = (int)$st->fetchColumn()) {
                db()->prepare('UPDATE clienti SET telefono = COALESCE(telefono, ?), data_nascita = COALESCE(data_nascita, ?), comune_id = COALESCE(comune_id, ?),
                    consenso_privacy = GREATEST(consenso_privacy, ?), aggiornato_il = UTC_TIMESTAMP() WHERE id = ?')
                    ->execute([($d['telefono'] ?? '') ?: null, ($d['data_nascita'] ?? '') ?: null, ($d['comune_id'] ?? 0) ?: null, (int)!empty($d['consenso']), $id]);
                return $id;
            }
        }
        db()->prepare('INSERT INTO clienti (nome, email, telefono, data_nascita, comune_id, stato_id, origine, consenso_privacy, creato_il, aggiornato_il)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')
            ->execute([$d['nome'], $email ?: null, ($d['telefono'] ?? '') ?: null, ($d['data_nascita'] ?? '') ?: null, ($d['comune_id'] ?? 0) ?: null,
                self::stato('nuovo'), $origine, (int)!empty($d['consenso'])]);
        return (int)db()->lastInsertId();
    }

    /** Cambia stato con il codice di uno stato di sistema (es. dopo l'invio o l'accettazione) */
    public static function impostaStato(int $clienteId, string $codice): void
    {
        $s = self::stato($codice);
        if ($s && $clienteId) db()->prepare('UPDATE clienti SET stato_id = ?, aggiornato_il = UTC_TIMESTAMP() WHERE id = ?')->execute([$s, $clienteId]);
    }
}
