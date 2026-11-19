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
        $f = db()->query('SELECT DISTINCT f.frazionamento FROM prodotti_frazionamenti f JOIN prodotti p ON p.id = f.prodotto_id WHERE p.preventivabile = 1 AND p.attivo = 1 AND (p.tasso IS NULL OR p.tasso = 0)')->fetchAll(PDO::FETCH_COLUMN);
        $d = db()->query('SELECT DISTINCT d.anni FROM prodotti_durate d JOIN prodotti p ON p.id = d.prodotto_id WHERE p.preventivabile = 1 AND p.attivo = 1 AND (p.tasso IS NULL OR p.tasso = 0) ORDER BY d.anni')->fetchAll(PDO::FETCH_COLUMN);
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
            $st = db()->query('SELECT p.*, c.nome AS compagnia FROM prodotti p JOIN compagnie c ON c.id = p.compagnia_id WHERE p.preventivabile = 1 AND p.attivo = 1 AND c.attivo = 1 AND (p.tasso IS NULL OR p.tasso = 0) ORDER BY c.nome, p.nome');
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
        foreach ($prodotti as &$p) { $p['domande'] = []; if (!$p['frazionamenti']) $p['frazionamenti'] = ['annuale' => 0.0]; }
        unset($p);
        $dom = [];
        foreach (db()->query("SELECT * FROM prodotti_domande WHERE prodotto_id IN ($in) ORDER BY ordine, id") as $d) $dom[(int)$d['id']] = $d + ['risposte' => []];
        if ($dom) foreach (db()->query('SELECT * FROM prodotti_risposte WHERE domanda_id IN (' . implode(',', array_keys($dom)) . ') ORDER BY ordine, id') as $r) $dom[(int)$r['domanda_id']]['risposte'][] = $r;
        foreach ($dom as $d) $prodotti[(int)$d['prodotto_id']]['domande'][] = $d;
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

    /** Categorie non auto con almeno un prodotto preventivabile (con tasso), per il sito */
    public static function categorieCapitale(bool $soloOnline = true): array
    {
        $auto = Categorie::perChiave('auto');
        $st = db()->query('SELECT DISTINCT p.categoria_id FROM prodotti p JOIN compagnie c ON c.id = p.compagnia_id LEFT JOIN categorie k ON k.id = p.categoria_id
            WHERE p.attivo = 1 AND c.attivo = 1 AND p.tasso > 0 AND p.categoria_id IS NOT NULL' . ($soloOnline ? ' AND p.preventivabile = 1' : '') . ($auto ? ' AND COALESCE(k.parent_id, k.id) <> ' . (int)$auto : ''));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) $out[] = ['id' => (int)$id, 'nome' => Categorie::etichetta((int)$id)];
        usort($out, fn($a, $b) => strcmp($a['nome'], $b['nome']));
        return $out;
    }

    /** Prodotti a capitale di una categoria (solo quelli online, se richiesto) */
    public static function prodottiCapitale(int $categoria, bool $soloOnline = true): array
    {
        $st = db()->prepare('SELECT p.id FROM prodotti p JOIN compagnie c ON c.id = p.compagnia_id WHERE p.attivo = 1 AND c.attivo = 1 AND p.tasso > 0 AND p.categoria_id = ?' . ($soloOnline ? ' AND p.preventivabile = 1' : ''));
        $st->execute([$categoria]);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
        return $ids ? self::listini($ids) : [];
    }

    /** Questionario comune ai prodotti: domande e risposte unite per testo, frazionamenti, capitale ammesso */
    public static function questionario(array $listini): array
    {
        $dom = []; $fraz = []; $min = null; $max = null;
        foreach ($listini as $p) {
            foreach ($p['domande'] as $d) {
                $k = Calcolo::norm($d['testo']);
                $dom[$k] ??= ['testo' => $d['testo'], 'aiuto' => $d['aiuto'], 'ordine' => (int)$d['ordine'], 'risposte' => []];
                foreach ($d['risposte'] as $r) $dom[$k]['risposte'][Calcolo::norm($r['testo'])] ??= $r['testo'];
            }
            foreach (array_keys($p['frazionamenti']) as $f) $fraz[$f] = true;
            if (!empty($p['capitale_minimo'])) $min = $min === null ? (float)$p['capitale_minimo'] : min($min, (float)$p['capitale_minimo']);
            if (!empty($p['capitale_massimo'])) $max = $max === null ? (float)$p['capitale_massimo'] : max($max, (float)$p['capitale_massimo']);
        }
        uasort($dom, fn($a, $b) => $a['ordine'] <=> $b['ordine']);
        $fr = [];
        foreach (self::FRAZIONAMENTI as $k => $n) if (isset($fraz[$k])) $fr[] = ['valore' => $k, 'nome' => $n];
        return ['domande' => array_values(array_map(fn($d) => ['testo' => $d['testo'], 'aiuto' => $d['aiuto'], 'risposte' => array_values($d['risposte'])], $dom)),
            'frazionamenti' => $fr ?: [['valore' => 'annuale', 'nome' => 'Annuale']], 'capitale_minimo' => $min, 'capitale_massimo' => $max];
    }

    /**
     * Polizze a capitale (vita, danni): calcola ogni prodotto e restituisce il migliore con il confronto.
     * @param array $listini prodotti già caricati (listini())
     */
    public static function calcolaCapitale(array $listini, float $capitale, array $risposte, string $fraz): array
    {
        if (!$listini) return [null, 'Al momento non ci sono tariffe per questo tipo di polizza.'];
        if (!isset(self::FRAZIONAMENTI[$fraz])) $fraz = 'annuale';
        if ($capitale <= 0) return [null, 'Indica il capitale da assicurare.'];
        $opz = ['arrotondamento' => impostazione('arrotondamento', 'centesimi')];
        $validi = []; $esclusi = [];
        foreach ($listini as $p) {
            [$r, $motivo] = Calcolo::capitale($p, $capitale, $risposte, ['frazionamento' => $fraz, 'oggi' => date('Y-m-d')], $opz);
            if ($r) $validi[] = $r;
            else $esclusi[] = ['prodotto_id' => (int)$p['id'], 'prodotto' => $p['nome'], 'compagnia' => $p['compagnia'], 'motivo' => $motivo];
        }
        if (!$validi) return [null, count($esclusi) === 1 ? $esclusi[0]['motivo'] . '.' : 'Nessuna tariffa copre questa combinazione: controlla capitale e risposte, oppure chiamaci.'];
        usort($validi, fn($a, $b) => $a['annuo'] <=> $b['annuo']);
        $r = $validi[0];
        $p0 = array_values(array_filter($listini, fn($x) => (int)$x['id'] === $r['prodotto_id']))[0];
        $r['totale'] = $r['annuo'];
        $r['categoria_id'] = (int)$p0['categoria_id'];
        $r['tipo_polizza'] = Categorie::etichetta((int)$p0['categoria_id']);
        $r['avviso'] = impostazione('avviso_polizze', 'Stima indicativa e non vincolante, calcolata sulle risposte inserite. Non è un\'offerta contrattuale: il premio definitivo dipende dalla compagnia, dal questionario sanitario o di rischio e dalla verifica dei dati in agenzia.');
        $r['confronto'] = ['validi' => array_map(fn($x) => array_intersect_key($x, array_flip(['prodotto_id', 'prodotto', 'compagnia', 'annuo', 'rata', 'rate', 'frazionamento', 'durata', 'su_richiesta', 'righe', 'sconti', 'correzioni', 'limite'])), $validi), 'esclusi' => $esclusi];
        return [$r, null];
    }
}
