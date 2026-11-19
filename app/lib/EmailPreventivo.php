<?php
declare(strict_types=1);

/**
 * Modello fisso delle email ai clienti e all'agenzia: HTML compatibile con i programmi di posta
 * (tabelle e stili in linea, larghezza 600px) più la versione solo testo.
 */
final class EmailPreventivo
{
    public const TELEFONO = '329 754 2063';
    public const TEL_LINK = '+393297542063';
    public const INDIRIZZO = 'Via Coazze 35, 10094 Giaveno (TO)';
    public const ORARI = 'Lunedì 15:30–19, da martedì a venerdì 9–12 e 15:30–19';
    public const SOCIETA = 'ACAT SERVIZI SRLS · P. IVA 12789310013';

    public static function sitoUrl(): string
    {
        if (empty($_SERVER['HTTP_HOST'])) {
            // da riga di comando (cron): l'indirizzo salvato dall'ultimo accesso al pannello
            $u = (string)impostazione('sito_url', '');
            if ($u !== '') return rtrim($u, '/');
        }
        $h = preg_replace('/[^a-z0-9.:-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || !str_contains($h, 'localhost') && !str_starts_with($h, '127.');
        return ($https ? 'https://' : 'http://') . $h;
    }

    /** Righe del preventivo da mostrare: quelle definitive se presenti, altrimenti quelle della stima */
    public static function righe(array $p): array
    {
        $def = json_decode((string)($p['righe_definitive'] ?? ''), true);
        if (is_array($def) && $def) return $def;
        $d = json_decode((string)($p['dettaglio'] ?? ''), true) ?: [];
        return array_map(fn($r) => ['nome' => $r['nome'], 'importo' => $r['importo']], array_merge($d['righe'] ?? [], $d['sconti'] ?? []));
    }

    public static function totale(array $p): ?float
    {
        return $p['totale_definitivo'] !== null && $p['totale_definitivo'] !== '' ? (float)$p['totale_definitivo'] : ($p['totale'] !== null ? (float)$p['totale'] : null);
    }

    /** @return array{oggetto:string, html:string, testo:string} */
    public static function preventivo(array $p, array $c, string $link): array
    {
        $nome = Clienti::saluto($c) ?: 'Gentile cliente';
        $righe = self::righe($p);
        $tot = self::totale($p);
        $msg = trim((string)($p['messaggio'] ?? '')) ?: (string)impostazione('messaggio_preventivo', '');
        $oggetto = 'Il tuo preventivo RC auto è pronto · ACAT Servizi (n. ' . (int)$p['id'] . ')';
        $dati = array_filter([
            'Veicolo' => $p['veicolo'] ?? null,
            'Residenza' => $p['comune_nome'] ?? null,
            'Compagnia' => $p['compagnia'] ?? null,
            'Decorrenza' => !empty($p['decorrenza']) ? date('d/m/Y', strtotime($p['decorrenza'])) : null,
        ]);
        $valido = !empty($p['valido_fino']) ? date('d/m/Y', strtotime($p['valido_fino'])) : null;
        $base = self::sitoUrl();
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $euro = fn($v) => $v === null ? 'su richiesta' : '€ ' . number_format((float)$v, 2, ',', '.');

        $rowsHtml = '';
        foreach ($righe as $r) {
            $rowsHtml .= '<tr><td style="padding:12px 0;border-bottom:1px solid #e3e8ef;font-size:15px;color:#0e2340;">' . $e($r['nome']) . '</td>'
                . '<td align="right" style="padding:12px 0;border-bottom:1px solid #e3e8ef;font-size:15px;color:#0e2340;white-space:nowrap;"><b>' . $euro($r['importo']) . '</b></td></tr>';
        }
        $datiHtml = '';
        foreach ($dati as $k => $v) {
            $datiHtml .= '<tr><td style="padding:4px 16px 4px 0;font-size:14px;color:#5a6880;white-space:nowrap;">' . $e($k) . '</td><td style="padding:4px 0;font-size:14px;color:#0e2340;">' . $e($v) . '</td></tr>';
        }
        $font = "font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;";
        $html = '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($oggetto) . '</title></head>'
. '<body style="margin:0;padding:0;background:#eef2f7;' . $font . '">'
. '<div style="display:none;max-height:0;overflow:hidden;">Il tuo preventivo: ' . $e($euro($tot)) . '. Accettalo con un clic o chiamaci.</div>'
. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;"><tr><td align="center" style="padding:24px 12px;">'
. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;">'
. '<tr><td style="padding:28px 32px 20px;border-bottom:1px solid #e3e8ef;"><a href="' . $e($base) . '/"><img src="' . $e($base) . '/assets/email/logo-acat.png" width="150" height="32" alt="ACAT Servizi" style="display:block;border:0;"></a></td></tr>'
. '<tr><td style="padding:32px 32px 8px;' . $font . '">'
.   '<p style="margin:0 0 6px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#b3302a;font-weight:bold;">Preventivo n. ' . (int)$p['id'] . '</p>'
.   '<h1 style="margin:0 0 16px;font-size:26px;line-height:1.25;color:#0e2340;font-weight:normal;font-family:Georgia,\'Times New Roman\',serif;">Ciao ' . $e($nome) . ', il tuo preventivo è pronto</h1>'
.   '<p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#33415a;">' . nl2br($e($msg)) . '</p>'
.   ($datiHtml ? '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">' . $datiHtml . '</table>' : '')
. '</td></tr>'
. '<tr><td style="padding:0 32px;' . $font . '"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rowsHtml
.   '<tr><td style="padding:18px 0 4px;font-size:15px;color:#0e2340;"><b>Totale annuo</b></td><td align="right" style="padding:18px 0 4px;font-size:30px;color:#0e2340;font-family:Georgia,\'Times New Roman\',serif;white-space:nowrap;">' . $euro($tot) . '</td></tr>'
.   ($valido ? '<tr><td colspan="2" style="padding:0 0 4px;font-size:13px;color:#5a6880;">Offerta valida fino al ' . $e($valido) . '</td></tr>' : '')
. '</table></td></tr>'
. '<tr><td style="padding:28px 32px 8px;' . $font . '" align="left">'
.   '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
.   '<td style="border-radius:8px;background:#b3302a;"><a href="' . $e($link) . '" style="display:inline-block;padding:14px 22px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;' . $font . '">Accetto il preventivo</a></td>'
.   '<td style="width:12px;"></td>'
.   '<td style="border-radius:8px;border:1px solid #c7d0dd;"><a href="tel:' . self::TEL_LINK . '" style="display:inline-block;padding:13px 20px;font-size:15px;color:#0e2340;text-decoration:none;' . $font . '">Chiama ' . self::TELEFONO . '</a></td>'
.   '</tr></table>'
.   '<p style="margin:14px 0 0;font-size:13px;color:#5a6880;">Puoi anche <a href="' . $e($link) . '" style="color:#0e2340;">vedere il preventivo online</a> o rispondere a questa email.</p>'
. '</td></tr>'
. '<tr><td style="padding:24px 32px 28px;' . $font . '"><p style="margin:0;font-size:12px;line-height:1.6;color:#7a8496;">Il premio è riferito ai dati che ci hai comunicato e diventa definitivo con la firma del contratto. Prima della firma ricevi la documentazione precontrattuale prevista dalla legge.</p></td></tr>'
. '<tr><td style="padding:20px 32px;background:#0e2340;' . $font . '"><p style="margin:0;font-size:13px;line-height:1.7;color:#c9d3e2;"><b style="color:#ffffff;">ACAT Servizi</b> · Assicurazioni e pratiche auto<br>' . self::INDIRIZZO . ' · Tel. ' . self::TELEFONO . '<br>' . self::ORARI . '</p>'
.   '<p style="margin:10px 0 0;font-size:11px;color:#8d9ab0;">' . self::SOCIETA . ' · <a href="' . $e($base) . '/privacy.html" style="color:#8d9ab0;">Privacy</a> · Ricevi questa email perché hai chiesto un preventivo.</p></td></tr>'
. '</table></td></tr></table></body></html>';

        $testo = "Ciao $nome, il tuo preventivo è pronto.\n\n$msg\n\n";
        foreach ($dati as $k => $v) $testo .= "$k: $v\n";
        $testo .= "\n";
        foreach ($righe as $r) $testo .= '- ' . $r['nome'] . ': ' . $euro($r['importo']) . "\n";
        $testo .= "\nTotale annuo: " . $euro($tot) . "\n" . ($valido ? "Offerta valida fino al $valido\n" : '')
            . "\nPer accettarlo apri questo link: $link\noppure chiamaci al " . self::TELEFONO . ".\n\n"
            . "ACAT Servizi · " . self::INDIRIZZO . "\n" . self::ORARI . "\n" . self::SOCIETA . "\n";
        return ['oggetto' => $oggetto, 'html' => $html, 'testo' => $testo];
    }

    /** Email semplice per l'agenzia (nuova richiesta, preventivo accettato) */
    public static function avviso(string $titolo, array $righe, string $link): array
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows = '';
        $testo = "$titolo\n\n";
        foreach ($righe as $k => $v) {
            $rows .= '<tr><td style="padding:6px 16px 6px 0;color:#5a6880;font-size:14px;vertical-align:top;white-space:nowrap;">' . $e($k) . '</td><td style="padding:6px 0;color:#0e2340;font-size:14px;">' . nl2br($e($v)) . '</td></tr>';
            $testo .= "$k: $v\n";
        }
        $testo .= "\nApri nel pannello: $link\n";
        $html = '<!doctype html><html lang="it"><head><meta charset="utf-8"></head><body style="margin:0;background:#eef2f7;font-family:Helvetica,Arial,sans-serif;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px;">'
            . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;background:#fff;border-radius:12px;"><tr><td style="padding:24px 28px;">'
            . '<h1 style="margin:0 0 16px;font-size:20px;color:#0e2340;">' . $e($titolo) . '</h1><table role="presentation" cellpadding="0" cellspacing="0">' . $rows . '</table>'
            . '<p style="margin:20px 0 0;"><a href="' . $e($link) . '" style="display:inline-block;padding:12px 18px;background:#b3302a;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Apri nel pannello</a></p>'
            . '</td></tr></table></td></tr></table></body></html>';
        return ['oggetto' => $titolo, 'html' => $html, 'testo' => $testo];
    }

    /** Avviso di scadenza: stesso stile delle altre email, testo scelto in Impostazioni */
    public static function avvisoScadenza(string $oggetto, string $testo, string $link, string $etichetta, bool $perAgenzia): array
    {
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $base = self::sitoUrl();
        $font = "font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;";
        $html = '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($oggetto) . '</title></head>'
. '<body style="margin:0;padding:0;background:#eef2f7;' . $font . '"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;"><tr><td align="center" style="padding:24px 12px;">'
. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;">'
. '<tr><td style="padding:24px 32px 18px;border-bottom:1px solid #e3e8ef;"><img src="' . $e($base) . '/assets/email/logo-acat.png" width="150" height="32" alt="ACAT Servizi" style="display:block;border:0;"></td></tr>'
. '<tr><td style="padding:28px 32px 8px;' . $font . '"><p style="margin:0 0 8px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#b3302a;font-weight:bold;">' . ($perAgenzia ? 'Scadenzario polizze' : 'Promemoria scadenza') . '</p>'
. '<h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#0e2340;font-weight:normal;font-family:Georgia,\'Times New Roman\',serif;">' . $e($oggetto) . '</h1>'
. '<p style="margin:0;font-size:15px;line-height:1.65;color:#33415a;">' . nl2br($e($testo)) . '</p></td></tr>'
. '<tr><td style="padding:24px 32px 32px;"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="border-radius:8px;background:#b3302a;"><a href="' . $e($link) . '" style="display:inline-block;padding:13px 20px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;' . $font . '">' . $e($etichetta) . '</a></td></tr></table></td></tr>'
. '<tr><td style="padding:18px 32px;background:#0e2340;' . $font . '"><p style="margin:0;font-size:13px;line-height:1.7;color:#c9d3e2;"><b style="color:#ffffff;">ACAT Servizi</b> · ' . self::INDIRIZZO . ' · Tel. ' . self::TELEFONO . '<br>' . self::ORARI . '</p>'
. ($perAgenzia ? '' : '<p style="margin:8px 0 0;font-size:11px;color:#8d9ab0;">' . self::SOCIETA . ' · <a href="' . $e($base) . '/privacy.html" style="color:#8d9ab0;">Privacy</a> · Ricevi questa email perché sei nostro cliente.</p>') . '</td></tr>'
. '</table></td></tr></table></body></html>';
        $plain = $testo . "\n\n" . $etichetta . ': ' . $link . "\n\nACAT Servizi · " . self::INDIRIZZO . ' · Tel. ' . self::TELEFONO . "\n";
        return ['oggetto' => $oggetto, 'html' => $html, 'testo' => $plain];
    }
}
