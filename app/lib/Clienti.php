<?php
declare(strict_types=1);

/** Anagrafica: persone e aziende, legami fra loro, cliente delle richieste dal sito, stati automatici. */
final class Clienti
{
    public const TIPI = ['persona' => 'Persona', 'azienda' => 'Azienda'];
    public const SITUAZIONI = ['attiva' => 'Attiva', 'in_scadenza' => 'In scadenza', 'scaduta' => 'Scaduta / da rinnovare', 'chiusa' => 'Chiusa'];

    /** "Mario Rossi" → ["Mario", "Rossi"]; il primo nome è la prima parola, il resto è il cognome */
    public static function dividiNome(string $completo): array
    {
        $p = preg_split('/\s+/u', trim($completo), 2) ?: [''];
        return [$p[0] ?? '', isset($p[1]) && $p[1] !== '' ? $p[1] : null];
    }

    /** Nome da mostrare: nome + cognome per le persone, ragione sociale per le aziende */
    public static function nomeCompleto(string $tipo, string $nome, ?string $cognome = null, ?string $ragione = null): string
    {
        return $tipo === 'azienda' ? trim((string)$ragione) : trim($nome . ' ' . (string)$cognome);
    }

    /** Come chiamare il cliente nelle email: il nome per le persone, la ragione sociale per le aziende */
    public static function saluto(array $c): string
    {
        if (($c['tipo'] ?? 'persona') === 'azienda') return trim((string)($c['nome'] ?? ''));
        return trim((string)($c['nome_proprio'] ?? '')) ?: (self::dividiNome((string)($c['nome'] ?? ''))[0]);
    }

    /**
     * Situazione di una polizza per lo storico: attiva, in scadenza (prossima scadenza entro $giorni),
     * scaduta (attiva ma senza scadenze future, oppure segnata come scaduta) o chiusa (disdetta, sostituita).
     */
    public static function situazione(string $stato, ?string $prossima, string $oggi, int $giorni = 30): string
    {
        if ($stato === 'scaduta') return 'scaduta';
        if ($stato !== 'attiva') return 'chiusa';
        if ($prossima === null || $prossima < $oggi) return 'scaduta';
        $lim = (new DateTimeImmutable($oggi))->modify("+$giorni days")->format('Y-m-d');
        return $prossima <= $lim ? 'in_scadenza' : 'attiva';
    }

    /** Polizze del cliente divise per area (Auto, Ramo danni, Ramo vita, non classificate), con situazione e prossima scadenza */
    public static function polizzePerArea(int $clienteId): array
    {
        $st = db()->prepare("SELECT p.*, co.nome AS compagnia, pr.nome AS prodotto, COALESCE(k.parent_id, k.id) AS area_id
            FROM polizze p JOIN compagnie co ON co.id = p.compagnia_id LEFT JOIN prodotti pr ON pr.id = p.prodotto_id LEFT JOIN categorie k ON k.id = p.categoria_id
            WHERE p.cliente_id = ? ORDER BY p.scadenza");
        $st->execute([$clienteId]);
        $aree = [];
        foreach (db()->query('SELECT id, nome FROM categorie WHERE parent_id IS NULL ORDER BY ordine, nome') as $a) $aree[(int)$a['id']] = ['nome' => $a['nome'], 'polizze' => []];
        $aree[0] = ['nome' => 'Non classificate', 'polizze' => []];
        $oggi = date('Y-m-d');
        $giorni = max(1, (int)impostazione('giorni_in_scadenza', '30'));
        $ordine = ['in_scadenza' => 0, 'scaduta' => 1, 'attiva' => 2, 'chiusa' => 3];
        foreach ($st->fetchAll() as $p) {
            $pr = $p['stato'] === 'attiva' ? Scadenze::prossima($p['scadenza'], $p['frazionamento']) : null;
            $p['prossima'] = $pr;
            $p['situazione'] = self::situazione($p['stato'], $pr['data'] ?? null, $oggi, $giorni);
            $a = isset($aree[(int)$p['area_id']]) ? (int)$p['area_id'] : 0;
            $aree[$a]['polizze'][] = $p;
        }
        foreach ($aree as &$a) usort($a['polizze'], fn($x, $y) => [$ordine[$x['situazione']], $x['prossima']['data'] ?? $x['scadenza']] <=> [$ordine[$y['situazione']], $y['prossima']['data'] ?? $y['scadenza']]);
        return $aree;
    }

    /** Aziende a cui è legata una persona, o persone legate a un'azienda */
    public static function legami(int $id, string $tipo): array
    {
        $sql = $tipo === 'azienda'
            ? 'SELECT c.id, c.nome, c.email, c.telefono, l.ruolo FROM clienti_legami l JOIN clienti c ON c.id = l.persona_id WHERE l.azienda_id = ? ORDER BY c.cognome, c.nome'
            : 'SELECT c.id, c.nome, c.email, c.telefono, l.ruolo FROM clienti_legami l JOIN clienti c ON c.id = l.azienda_id WHERE l.persona_id = ? ORDER BY c.nome';
        $st = db()->prepare($sql);
        $st->execute([$id]);
        return $st->fetchAll();
    }

    public static function lega(int $personaId, int $aziendaId, ?string $ruolo): bool
    {
        $st = db()->prepare("SELECT (SELECT tipo FROM clienti WHERE id = ?) = 'persona' AND (SELECT tipo FROM clienti WHERE id = ?) = 'azienda'");
        $st->execute([$personaId, $aziendaId]);
        if (!(int)$st->fetchColumn()) return false;
        db()->prepare('INSERT INTO clienti_legami (persona_id, azienda_id, ruolo) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE ruolo = VALUES(ruolo)')->execute([$personaId, $aziendaId, $ruolo ?: null]);
        return true;
    }
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
        [$np, $cg] = self::dividiNome((string)$d['nome']);
        db()->prepare('INSERT INTO clienti (tipo, nome, nome_proprio, cognome, email, telefono, data_nascita, comune_id, stato_id, origine, consenso_privacy, creato_il, aggiornato_il)
            VALUES (\'persona\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')
            ->execute([trim((string)$d['nome']), $np, $cg, $email ?: null, ($d['telefono'] ?? '') ?: null, ($d['data_nascita'] ?? '') ?: null, ($d['comune_id'] ?? 0) ?: null,
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
