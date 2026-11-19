<?php
declare(strict_types=1);

/**
 * Legge dal database i dati che servono al calcolo e li passa a Calcolo.
 * Ogni prodotto "preventivabile" ha il suo listino (garanzie, frazionamenti, durate, promozioni, correzioni):
 * la stima si calcola per ciascun prodotto e al cliente si mostra il prezzo migliore.
 */
final class Preventivatore
{
    public const FRAZIONAMENTI = ['annuale' => 'Annuale', 'semestrale' => 'Semestrale', 'trimestrale' => 'Trimestrale', 'mensile' => 'Mensile'];

    /** Garanzie di un prodotto (o di tutti i prodotti preventivabili se $prodottoId è null) */
    public static function garanzie(?int $prodottoId = null, bool $soloAttive = true): array
    {
        $sql = 'SELECT g.id, g.prodotto_id, g.codice, g.nome, g.descrizione, g.principale, g.dipende_da, g.tipo_calcolo, g.valore, g.applica_coefficienti, g.ordine, g.attivo FROM garanzie g';
        $w = []; $a = [];
        if ($prodottoId !== null) { $w[] = 'g.prodotto_id = ?'; $a[] = $prodottoId; }
        else $w[] = 'g.prodotto_id IN (SELECT id FROM prodotti WHERE preventivabile = 1 AND attivo = 1)';
        if ($soloAttive) $w[] = 'g.attivo = 1';
        $st = db()->prepare($sql . ' WHERE ' . implode(' AND ', $w) . ' ORDER BY g.principale DESC, g.ordine, g.nome');
        $st->execute($a);
        return $st->fetchAll();
    }

    /** Garanzie proponibili al cliente: unione per codice fra i prodotti preventivabili (la principale è sempre inclusa) */
    public static function garanziePubbliche(): array
    {
        $out = [];
        foreach (self::garanzie() as $g) {
            $c = strtoupper($g['codice']);
            if (!isset($out[$c])) $out[$c] = ['codice' => $c, 'nome' => $g['nome'], 'descrizione' => $g['descrizione'], 'principale' => (int)$g['principale'] === 1, 'ordine' => (int)$g['ordine']];
        }
        uasort($out, fn($x, $y) => [$y['principale'], $x['ordine'], $x['nome']] <=> [$x['principale'], $y['ordine'], $y['nome']]);
        return array_values(array_map(function ($g) { unset($g['ordine']); return $g; }, $out));
    }

    /** Frazionamenti e durate offerti da almeno un prodotto preventivabile */
    public static function opzioniPagamento(): array
    {
        $f = db()->query('SELECT DISTINCT f.frazionamento FROM prodotti_frazionamenti f JOIN prodotti p ON p.id = f.prodotto_id WHERE p.preventivabile = 1 AND p.attivo = 1')->fetchAll(PDO::FETCH_COLUMN);
        $d = db()->query('SELECT DISTINCT d.anni FROM prodotti_durate d JOIN prodotti p ON p.id = d.prodotto_id WHERE p.preventivabile = 1 AND p.attivo = 1 ORDER BY d.anni')->fetchAll(PDO::FETCH_COLUMN);
        $fr = [];
        foreach (self::FRAZIONAMENTI as $k => $n) if (in_array($k, $f, true)) $fr[] = ['valore' => $k, 'nome' => $n];
        $du = array_values(array_unique(array_merge([1], array_map('intval', $d))));
        sort($du);
        return ['frazionamenti' => $fr ?: [['valore' => 'annuale', 'nome' => 'Annuale']], 'durate' => $du];
    }

    public static function fasce(): array
    {
        return db()->query('SELECT eta_min, eta_max, tipo, valore, descrizione FROM fasce_eta WHERE attivo = 1 ORDER BY eta_min')->fetchAll();
    }

    /** Listino completo dei prodotti (preventivabili, o il solo $id) per Calcolo::prodotto */
    public static function listini(int|array|null $id = null): array
    {
        if (is_array($id)) {
            $ids = array_values(array_filter(array_map('intval', $id)));
            if (!$ids) return [];
            $st = db()->query('SELECT p.*, c.nome AS compagnia FROM prodotti p JOIN compagnie c ON c.id = p.compagnia_id WHERE p.id IN (' . implode(',', $ids) . ') ORDER BY c.nome, p.nome');
        } elseif ($id !== null) {
            $st = db()->prepare('SELECT p.*, c.nome AS compagnia FROM prodotti p JOIN compagnie c ON c.id = p.compagnia_id WHERE p.id = ?');
            $st->execute([$id]);
        } else {
            $st = db()->query('SELECT p.*, c.nome AS compagnia FROM prodotti p JOIN compagnie c ON c.id = p.compagnia_id WHERE p.preventivabile = 1 AND p.attivo = 1 AND c.attivo = 1 ORDER BY c.nome, p.nome');
        }
        $prodotti = [];
        foreach ($st->fetchAll() as $p) { $p['garanzie'] = []; $p['frazionamenti'] = []; $p['durate'] = [1 => 0.0]; $p['promozioni'] = []; $p['correzioni'] = []; $prodotti[(int)$p['id']] = $p; }
        if (!$prodotti) return [];
        $in = implode(',', array_keys($prodotti));
        foreach (db()->query("SELECT * FROM garanzie WHERE prodotto_id IN ($in) ORDER BY principale DESC, ordine, nome") as $r) $prodotti[(int)$r['prodotto_id']]['garanzie'][] = $r;
        foreach (db()->query("SELECT * FROM prodotti_frazionamenti WHERE prodotto_id IN ($in)") as $r) $prodotti[(int)$r['prodotto_id']]['frazionamenti'][$r['frazionamento']] = (float)$r['variazione'];
        foreach (db()->query("SELECT * FROM prodotti_durate WHERE prodotto_id IN ($in) ORDER BY anni") as $r) $prodotti[(int)$r['prodotto_id']]['durate'][(int)$r['anni']] = (float)$r['sconto'];
        foreach (db()->query("SELECT * FROM promozioni WHERE prodotto_id IN ($in) ORDER BY id") as $r) $prodotti[(int)$r['prodotto_id']]['promozioni'][] = $r;
        foreach (db()->query("SELECT * FROM prodotti_correzioni WHERE prodotto_id IN ($in) ORDER BY id") as $r) $prodotti[(int)$r['prodotto_id']]['correzioni'][] = $r;
        foreach ($prodotti as &$p) if (!$p['frazionamenti']) $p['frazionamenti'] = ['annuale' => 0.0];
        return $prodotti;
    }

    /**
     * @param array $scelte codici delle garanzie accessorie
     * @param array $pag    frazionamento, durata, prodotto_id (per forzare un solo prodotto, anche non preventivabile)
     * @return array{0:?array,1:?string} [risultato del prodotto migliore + confronto, errore]
     */
    public static function calcola(int $comuneId, string $nascita, int $modelloId, array $scelte, bool $ancheInattivi = false, array $pag = []): array
    {
        $eta = eta_da_data($nascita);
        $min = (int)impostazione('eta_minima', '18');
        if ($eta === null) return [null, 'Data di nascita non valida.'];
        if ($eta < $min) return [null, "Per la stima online il contraente deve avere almeno $min anni."];
        if ($eta > 110) return [null, 'Data di nascita non valida.'];

        $att = $ancheInattivi ? '' : ' AND attivo = 1';
        $st = db()->prepare('SELECT id, nome, sigla, regione, coefficiente FROM comuni WHERE id = ?' . $att);
        $st->execute([$comuneId]);
        $comune = $st->fetch();
        if (!$comune) return [null, 'Scegli il comune di residenza dall\'elenco.'];

        $st = db()->prepare('SELECT mo.id, mo.nome, mo.coefficiente, mo.valore_indicativo, ma.id AS marca_id, ma.nome AS marca, ma.coefficiente AS coeff_marca, ma.tipo_veicolo
            FROM modelli mo JOIN marche ma ON ma.id = mo.marca_id WHERE mo.id = ?' . ($ancheInattivi ? '' : ' AND mo.attivo = 1 AND ma.attivo = 1'));
        $st->execute([$modelloId]);
        $mod = $st->fetch();
        if (!$mod) return [null, 'Scegli marca e modello del veicolo.'];

        $fraz = (string)($pag['frazionamento'] ?? 'annuale');
        if (!isset(self::FRAZIONAMENTI[$fraz])) $fraz = 'annuale';
        $durata = max(1, min(10, (int)($pag['durata'] ?? 1)));
        $ctx = [
            'coeff_comune' => (float)$comune['coefficiente'], 'coeff_marca' => (float)$mod['coeff_marca'], 'coeff_modello' => (float)$mod['coefficiente'],
            'valore' => $mod['valore_indicativo'] !== null ? (float)$mod['valore_indicativo'] : null,
            'sigla' => $comune['sigla'], 'regione' => $comune['regione'], 'marca_id' => (int)$mod['marca_id'], 'tipo_veicolo' => $mod['tipo_veicolo'],
            'frazionamento' => $fraz, 'durata' => $durata, 'oggi' => date('Y-m-d'),
        ];
        $opz = ['premio_minimo_rc' => (float)impostazione('premio_minimo_rc', '0'), 'arrotondamento' => impostazione('arrotondamento', 'centesimi')];
        $scelte = array_values(array_unique(array_map(fn($c) => strtoupper((string)$c), $scelte)));
        $fasce = self::fasce();

        $listini = self::listini(isset($pag['prodotto_id']) ? (int)$pag['prodotto_id'] : ($pag['prodotti'] ?? null));
        if (!$listini) return [null, 'Al momento non ci sono tariffe disponibili per la stima online: chiamaci o scrivici.'];
        $validi = []; $esclusi = [];
        foreach ($listini as $p) {
            [$r, $motivo] = Calcolo::prodotto($p, $scelte, $eta, $fasce, $ctx, $opz);
            if ($r) $validi[] = $r;
            else $esclusi[] = ['prodotto_id' => (int)$p['id'], 'prodotto' => $p['nome'], 'compagnia' => $p['compagnia'], 'motivo' => $motivo];
        }
        if (!$validi) return [null, 'Nessuna delle nostre tariffe online copre questa combinazione di garanzie e pagamento: prova a togliere qualche garanzia o a cambiare il frazionamento, oppure chiamaci.'];
        usort($validi, fn($a, $b) => [$a['su_richiesta'], $a['annuo']] <=> [$b['su_richiesta'], $b['annuo']]);

        $r = $validi[0];
        $r['totale'] = $r['annuo'];
        $r['eta'] = $eta;
        $r['comune'] = $comune['nome'] . ' (' . $comune['sigla'] . ')';
        $r['veicolo'] = $mod['marca'] . ' ' . $mod['nome'];
        $r['avviso'] = impostazione('avviso', '');
        $r['confronto'] = ['validi' => array_map(fn($x) => array_intersect_key($x, array_flip(['prodotto_id', 'prodotto', 'compagnia', 'annuo', 'rata', 'rate', 'frazionamento', 'durata', 'su_richiesta', 'righe', 'sconti', 'correzioni', 'limite'])), $validi), 'esclusi' => $esclusi];
        return [$r, null];
    }
}
