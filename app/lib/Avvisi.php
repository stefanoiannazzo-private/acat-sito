<?php
declare(strict_types=1);

/**
 * Invio degli avvisi di scadenza delle polizze. Lo chiama ogni ora il cron dell'hosting
 * (cron/scadenze.php) e, come riserva, il pannello quando viene aperto. Non invia mai due volte
 * lo stesso avviso (tabella avvisi_scadenza); in caso di errore riprova fino a 3 volte.
 */
final class Avvisi
{
    public static function config(): array
    {
        return [
            'attivi' => impostazione('avvisi_attivi', '1') === '1',
            'cliente' => impostazione('avvisi_destinatari', 'agenzia') === 'agenzia_cliente',
            'giorni_agenzia' => (int)impostazione('avvisi_giorni_agenzia', '15'),
            'giorni_cliente' => (int)impostazione('avvisi_giorni_cliente', '15'),
            'ora' => (int)impostazione('avvisi_ora', '9'),
            'rate' => impostazione('avvisi_rate', '1') === '1',
            'email_agenzia' => trim((string)(impostazione('avvisi_email_agenzia', '') ?: impostazione('email_notifiche', ''))),
        ];
    }

    /** @return array{inviati:int, errori:int, saltati:int} */
    public static function esegui(?DateTimeImmutable $adesso = null): array
    {
        $cfg = self::config();
        $out = ['inviati' => 0, 'errori' => 0, 'saltati' => 0];
        if (!$cfg['attivi']) return $out;
        $adesso ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Rome'));
        $lock = (int)db()->query("SELECT GET_LOCK('acat_avvisi', 0)")->fetchColumn();
        if ($lock !== 1) return $out;
        try {
            $st = db()->query("SELECT p.*, c.nome AS cliente, c.email AS email_cliente, c.telefono AS telefono_cliente, co.nome AS compagnia, pr.nome AS prodotto
                FROM polizze p JOIN clienti c ON c.id = p.cliente_id JOIN compagnie co ON co.id = p.compagnia_id LEFT JOIN prodotti pr ON pr.id = p.prodotto_id
                WHERE p.stato = 'attiva' AND p.scadenza >= CURDATE() - INTERVAL 1 DAY");
            $gia = db()->prepare('SELECT esito, tentativi FROM avvisi_scadenza WHERE polizza_id = ? AND evento = ? AND data_evento = ? AND destinatario = ?');
            foreach ($st as $p) {
                foreach (Scadenze::daInviare($p, $cfg, $adesso) as $a) {
                    $gia->execute([$p['id'], $a['evento'], $a['data'], $a['destinatario']]);
                    $prec = $gia->fetch();
                    if ($prec && ($prec['esito'] === 'inviata' || $prec['esito'] === 'saltata' || (int)$prec['tentativi'] >= 3)) continue;
                    [$esito, $email, $errore] = self::invia($p, $a, $cfg);
                    db()->prepare('INSERT INTO avvisi_scadenza (polizza_id, evento, data_evento, destinatario, email, esito, errore, creato_il) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
                        ON DUPLICATE KEY UPDATE esito = VALUES(esito), errore = VALUES(errore), email = VALUES(email), tentativi = tentativi + 1, creato_il = UTC_TIMESTAMP()')
                        ->execute([$p['id'], $a['evento'], $a['data'], $a['destinatario'], $email, $esito, $errore]);
                    $out[$esito === 'inviata' ? 'inviati' : ($esito === 'saltata' ? 'saltati' : 'errori')]++;
                }
            }
        } finally {
            db()->query("SELECT RELEASE_LOCK('acat_avvisi')");
        }
        db()->prepare("INSERT INTO impostazioni (chiave, valore) VALUES ('avvisi_ultimo_controllo', ?) ON DUPLICATE KEY UPDATE valore = VALUES(valore)")->execute([gmdate('Y-m-d H:i:s')]);
        return $out;
    }

    /** Valori per i segnaposto dei testi */
    public static function valori(array $p, array $a): array
    {
        return [
            'cliente' => $p['cliente'], 'compagnia' => $p['compagnia'], 'prodotto' => $p['prodotto'] ?: 'assicurativa',
            'numero' => $p['numero'] ? ' (n. ' . $p['numero'] . ')' : '', 'scadenza' => date('d/m/Y', strtotime($a['data'])),
            'tipo_scadenza' => $a['evento'] === 'annualita' ? 'annualità' : 'rata ' . strtolower(Scadenze::NOMI[$p['frazionamento']] ?? ''),
            'frazionamento' => strtolower(Scadenze::NOMI[$p['frazionamento']] ?? $p['frazionamento']),
            'premio' => $p['premio_annuo'] !== null ? '€ ' . number_format((float)$p['premio_annuo'], 2, ',', '.') : 'non indicato',
            'oggetto' => (string)$p['oggetto'], 'email_cliente' => $p['email_cliente'] ?: 'email non presente', 'telefono_cliente' => $p['telefono_cliente'] ?: 'telefono non presente',
        ];
    }

    /** @return array{0:string,1:?string,2:?string} [esito, email, errore] */
    private static function invia(array $p, array $a, array $cfg): array
    {
        $v = self::valori($p, $a);
        if ($a['destinatario'] === 'agenzia') {
            $to = $cfg['email_agenzia'];
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return ['errore', null, 'Manca l\'email dell\'agenzia per gli avvisi (Impostazioni).'];
            $ogg = Scadenze::compila((string)impostazione('avvisi_oggetto_agenzia', ''), $v);
            $txt = Scadenze::compila((string)impostazione('avvisi_testo_agenzia', ''), $v);
            $m = EmailPreventivo::avvisoScadenza($ogg, $txt, EmailPreventivo::sitoUrl() . '/admin/clienti.php?id=' . $p['cliente_id'], 'Apri la scheda del cliente', true);
            $err = Mailer::invia($to, $m['oggetto'], $m['html'], $m['testo'], $p['email_cliente'] ?: null, 'Scadenze ACAT');
        } else {
            $to = (string)$p['email_cliente'];
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return ['saltata', null, 'Il cliente non ha un\'email valida.'];
            $ogg = Scadenze::compila((string)impostazione('avvisi_oggetto_cliente', ''), $v);
            $txt = Scadenze::compila((string)impostazione('avvisi_testo_cliente', ''), $v);
            $m = EmailPreventivo::avvisoScadenza($ogg, $txt, 'tel:' . EmailPreventivo::TEL_LINK, 'Chiama ' . EmailPreventivo::TELEFONO, false);
            $err = Mailer::invia($to, $m['oggetto'], $m['html'], $m['testo'], trim((string)impostazione('email_risposta', '')) ?: null);
        }
        Mailer::registra((int)$p['cliente_id'], null, 'scadenza_' . $a['destinatario'], $to, $m['oggetto'], $err, 'automatico');
        return [$err === null ? 'inviata' : 'errore', $to, $err];
    }

    /** Riserva: se il cron non gira, il pannello controlla al massimo una volta ogni ora */
    public static function riserva(): void
    {
        $ultimo = (string)impostazione('avvisi_ultimo_controllo', '');
        if ($ultimo !== '' && strtotime($ultimo . ' UTC') > time() - 3600) return;
        try { self::esegui(); } catch (Throwable $e) { error_log('[avvisi] ' . $e->getMessage()); }
    }
}
