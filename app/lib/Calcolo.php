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
}
