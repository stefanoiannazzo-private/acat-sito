<?php
declare(strict_types=1);

/**
 * Calcolo della stima. Classe pura (nessun accesso al database), così è verificabile con i test.
 *
 * RC = base RC × coefficiente comune × coefficiente marca × coefficiente modello,
 *      poi supplemento della fascia d'età (percentuale o importo), poi premio minimo.
 * Ogni altra garanzia richiede l'RC e vale, secondo il suo tipo di calcolo:
 *   fisso        importo fisso (× coefficienti se "applica_coefficienti")
 *   perc_rc      percentuale dell'RC calcolata sopra
 *   perc_valore  percentuale del valore indicativo del veicolo (se manca: "su richiesta")
 */
final class Calcolo
{
    /**
     * @param array $garanzie   tutte le garanzie attive: [id, codice, nome, principale, dipende_da, tipo_calcolo, valore, applica_coefficienti]
     * @param array $scelte     id delle garanzie accessorie scelte
     * @param array $fasce      fasce d'età attive: [eta_min, eta_max, tipo, valore, descrizione]
     * @param array $opzioni    premio_minimo_rc, arrotondamento ('euro'|'centesimi')
     */
    public static function stima(
        array $garanzie, array $scelte, int $eta, array $fasce,
        float $coeffComune, float $coeffMarca, float $coeffModello, ?float $valoreVeicolo, array $opzioni = []
    ): array {
        $rc = null;
        foreach ($garanzie as $g) if ((int)$g['principale'] === 1) { $rc = $g; break; }
        if ($rc === null) throw new InvalidArgumentException('Manca la garanzia principale (RC).');

        $coeff = $coeffComune * $coeffMarca * $coeffModello;
        $base = (float)$rc['valore'];
        $premioRc = $base * $coeff;
        $righe = [];

        $fascia = null;
        foreach ($fasce as $f) if ($eta >= (int)$f['eta_min'] && $eta <= (int)$f['eta_max']) { $fascia = $f; break; }
        $supplemento = 0.0;
        if ($fascia) {
            $supplemento = $fascia['tipo'] === 'percentuale' ? $premioRc * (float)$fascia['valore'] / 100 : (float)$fascia['valore'];
            $premioRc += $supplemento;
        }
        $minimo = (float)($opzioni['premio_minimo_rc'] ?? 0);
        $minimoApplicato = $minimo > 0 && $premioRc < $minimo;
        if ($minimoApplicato) $premioRc = $minimo;
        $premioRc = self::arrotonda($premioRc, $opzioni);

        $righe[] = [
            'id' => (int)$rc['id'], 'codice' => $rc['codice'], 'nome' => $rc['nome'], 'importo' => $premioRc, 'principale' => true,
            'spiegazione' => sprintf('Base %s × coefficienti %s%s%s', self::n($base), self::n($coeff, 3),
                $fascia && (float)$fascia['valore'] != 0 ? ' + supplemento età ' . ($fascia['tipo'] === 'percentuale' ? self::n((float)$fascia['valore']) . '%' : self::n((float)$fascia['valore']) . ' €') : '',
                $minimoApplicato ? ' (premio minimo)' : ''),
        ];

        $totale = $premioRc;
        $suRichiesta = false;
        $scelte = array_map('intval', $scelte);
        foreach ($garanzie as $g) {
            if ((int)$g['principale'] === 1 || !in_array((int)$g['id'], $scelte, true)) continue;
            if ((int)$g['dipende_da'] !== (int)$rc['id']) continue; // ogni accessoria deve essere legata all'RC
            $v = (float)$g['valore'];
            $importo = null;
            switch ($g['tipo_calcolo']) {
                case 'fisso':
                    $importo = $v * ((int)$g['applica_coefficienti'] === 1 ? $coeff : 1);
                    $sp = (int)$g['applica_coefficienti'] === 1 ? 'Importo fisso × coefficienti' : 'Importo fisso';
                    break;
                case 'perc_rc':
                    $importo = $premioRc * $v / 100;
                    $sp = self::n($v) . '% dell\'RC';
                    break;
                case 'perc_valore':
                    if ($valoreVeicolo !== null && $valoreVeicolo > 0) { $importo = $valoreVeicolo * $v / 100; $sp = self::n($v) . '% del valore del veicolo'; }
                    else { $sp = 'Su richiesta: serve il valore del veicolo'; $suRichiesta = true; }
                    break;
                default:
                    $sp = 'Tipo di calcolo sconosciuto';
            }
            if ($importo !== null) { $importo = self::arrotonda($importo, $opzioni); $totale += $importo; }
            $righe[] = ['id' => (int)$g['id'], 'codice' => $g['codice'], 'nome' => $g['nome'], 'importo' => $importo, 'principale' => false, 'spiegazione' => $sp];
        }

        return [
            'righe' => $righe,
            'totale' => round($totale, 2),
            'su_richiesta' => $suRichiesta,
            'fascia_eta' => $fascia['descrizione'] ?? null,
            'coefficiente' => round($coeff, 4),
        ];
    }

    private static function arrotonda(float $v, array $o): float
    {
        return ($o['arrotondamento'] ?? 'centesimi') === 'euro' ? round($v, 0) : round($v, 2);
    }

    private static function n(float $v, int $dec = 2): string
    {
        return rtrim(rtrim(number_format($v, $dec, ',', '.'), '0'), ',');
    }

    public const RATE = ['annuale' => 1, 'semestrale' => 2, 'trimestrale' => 4, 'mensile' => 12];

    /**
     * Premio di un prodotto per il cliente: garanzie del prodotto (calcolo di stima()), poi
     * correzioni del prodotto sull'RC, promozioni attive, sconto per la durata, premio minimo/massimo
     * e infine la maggiorazione o lo sconto del frazionamento.
     *
     * @param array $p   prodotto: id, nome, compagnia, premio_minimo, premio_massimo,
     *                   frazionamenti [fraz => variazione %], durate [anni => sconto %],
     *                   promozioni [[nome, tipo, valore, dal, al]], correzioni [[tipo, eta_min, eta_max, chiave, moltiplicatore, descrizione]],
     *                   garanzie (righe della tabella garanzie del prodotto), veicoli (auto|moto|entrambi)
     * @param array $codici  codici delle garanzie accessorie scelte (es. FURTO, CRISTALLI)
     * @param array $ctx     coeff_comune, coeff_marca, coeff_modello, valore, sigla, regione, marca_id, tipo_veicolo, frazionamento, durata, oggi
     * @return array{0:?array,1:?string} [risultato, motivo dell'esclusione]
     */
    public static function prodotto(array $p, array $codici, int $eta, array $fasce, array $ctx, array $opz = []): array
    {
        $fraz = $ctx['frazionamento'] ?? 'annuale';
        $durata = (int)($ctx['durata'] ?? 1);
        $oggi = $ctx['oggi'] ?? date('Y-m-d');
        if (($p['veicoli'] ?? 'entrambi') !== 'entrambi' && $p['veicoli'] !== ($ctx['tipo_veicolo'] ?? 'auto')) return [null, 'Non assicura questo tipo di veicolo'];
        if (!array_key_exists($fraz, $p['frazionamenti'] ?? [])) return [null, 'Frazionamento ' . $fraz . ' non previsto'];
        $durate = $p['durate'] ?? [1 => 0];
        if ($durata > 1 && !array_key_exists($durata, $durate)) return [null, "Durata di $durata anni non prevista"];

        $perCodice = [];
        foreach ($p['garanzie'] as $g) if ((int)($g['attivo'] ?? 1) === 1) $perCodice[strtoupper((string)$g['codice'])] = $g;
        $ids = [];
        foreach ($codici as $c) {
            $c = strtoupper((string)$c);
            if (!isset($perCodice[$c]) || (int)$perCodice[$c]['principale'] === 1) return [null, 'Garanzia ' . $c . ' non presente'];
            $ids[] = (int)$perCodice[$c]['id'];
        }

        // correzioni del prodotto (moltiplicano l'RC e le garanzie che applicano i coefficienti)
        $molt = 1.0; $applicate = [];
        foreach ($p['correzioni'] ?? [] as $c) {
            $ok = match ($c['tipo']) {
                'eta' => $eta >= (int)$c['eta_min'] && $eta <= (int)$c['eta_max'],
                'provincia' => strcasecmp((string)$c['chiave'], (string)($ctx['sigla'] ?? '')) === 0,
                'regione' => strcasecmp((string)$c['chiave'], (string)($ctx['regione'] ?? '')) === 0,
                'marca' => (int)$c['chiave'] === (int)($ctx['marca_id'] ?? 0),
                'veicolo' => (string)$c['chiave'] === (string)($ctx['tipo_veicolo'] ?? ''),
                default => false,
            };
            if ($ok) { $molt *= (float)$c['moltiplicatore']; $applicate[] = ($c['descrizione'] ?: $c['tipo']) . ' ×' . rtrim(rtrim(number_format((float)$c['moltiplicatore'], 3, ',', ''), '0'), ','); }
        }

        try {
            $s = self::stima(array_values($perCodice), $ids, $eta, $fasce, (float)$ctx['coeff_comune'], (float)$ctx['coeff_marca'], (float)$ctx['coeff_modello'] * $molt,
                isset($ctx['valore']) && $ctx['valore'] !== null ? (float)$ctx['valore'] : null, $opz);
        } catch (InvalidArgumentException) {
            return [null, 'Manca la garanzia RC'];
        }
        $arr = fn(float $v) => ($opz['arrotondamento'] ?? 'centesimi') === 'euro' ? round($v, 0) : round($v, 2);
        $base = $s['totale'];
        $annuo = $base;
        $sconti = [];
        foreach ($p['promozioni'] ?? [] as $pr) {
            if ((int)($pr['attivo'] ?? 1) !== 1) continue;
            if (!empty($pr['dal']) && $pr['dal'] > $oggi) continue;
            if (!empty($pr['al']) && $pr['al'] < $oggi) continue;
            $d = $pr['tipo'] === 'importo' ? min($annuo, (float)$pr['valore']) : $annuo * (float)$pr['valore'] / 100;
            $annuo -= $d;
            $sconti[] = ['nome' => $pr['nome'], 'importo' => -$arr($d)];
        }
        if ($durata > 1 && ($sc = (float)($durate[$durata] ?? 0)) != 0) {
            $d = $annuo * $sc / 100;
            $annuo -= $d;
            $sconti[] = ['nome' => "Sconto per durata di $durata anni (" . rtrim(rtrim(number_format($sc, 2, ',', ''), '0'), ',') . '%)', 'importo' => -$arr($d)];
        }
        $limite = null;
        if (!empty($p['premio_minimo']) && $annuo < (float)$p['premio_minimo']) { $annuo = (float)$p['premio_minimo']; $limite = 'premio minimo del prodotto'; }
        if (!empty($p['premio_massimo']) && $annuo > (float)$p['premio_massimo']) { $annuo = (float)$p['premio_massimo']; $limite = 'premio massimo del prodotto'; }
        $var = (float)$p['frazionamenti'][$fraz];
        if ($var != 0) {
            $d = $annuo * $var / 100;
            $annuo += $d;
            $sconti[] = ['nome' => ($var > 0 ? 'Maggiorazione' : 'Sconto') . ' pagamento ' . $fraz . ' (' . ($var > 0 ? '+' : '') . rtrim(rtrim(number_format($var, 2, ',', ''), '0'), ',') . '%)', 'importo' => $arr($d)];
        }
        $annuo = $arr(max(0, $annuo));
        $n = self::RATE[$fraz] ?? 1;
        return [[
            'prodotto_id' => (int)$p['id'], 'prodotto' => $p['nome'], 'compagnia' => $p['compagnia'] ?? '',
            'righe' => $s['righe'], 'sconti' => $sconti, 'premio_garanzie' => $base, 'annuo' => $annuo,
            'frazionamento' => $fraz, 'rate' => $n, 'rata' => round($annuo / $n, 2), 'durata' => $durata, 'totale_durata' => round($annuo * $durata, 2),
            'su_richiesta' => $s['su_richiesta'], 'fascia_eta' => $s['fascia_eta'], 'coefficiente' => $s['coefficiente'],
            'correzioni' => $applicate, 'limite' => $limite,
        ], null];
    }
}
