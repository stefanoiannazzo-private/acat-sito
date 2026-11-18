<?php
declare(strict_types=1);

/** Legge dal database i dati che servono al calcolo e li passa a Calcolo. */
final class Preventivatore
{
    public static function garanzie(bool $soloAttive = true): array
    {
        return db()->query('SELECT id, codice, nome, descrizione, principale, dipende_da, tipo_calcolo, valore, applica_coefficienti, ordine, attivo FROM garanzie'
            . ($soloAttive ? ' WHERE attivo = 1' : '') . ' ORDER BY principale DESC, ordine, nome')->fetchAll();
    }

    public static function fasce(): array
    {
        return db()->query('SELECT eta_min, eta_max, tipo, valore, descrizione FROM fasce_eta WHERE attivo = 1 ORDER BY eta_min')->fetchAll();
    }

    /** @return array{0:?array,1:?string} [risultato, errore] */
    public static function calcola(int $comuneId, string $nascita, int $modelloId, array $scelte, bool $ancheInattivi = false): array
    {
        $eta = eta_da_data($nascita);
        $min = (int)impostazione('eta_minima', '18');
        if ($eta === null) return [null, 'Data di nascita non valida.'];
        if ($eta < $min) return [null, "Per la stima online il contraente deve avere almeno $min anni."];
        if ($eta > 110) return [null, 'Data di nascita non valida.'];

        $att = $ancheInattivi ? '' : ' AND attivo = 1';
        $st = db()->prepare('SELECT id, nome, sigla, coefficiente FROM comuni WHERE id = ?' . $att);
        $st->execute([$comuneId]);
        $comune = $st->fetch();
        if (!$comune) return [null, 'Scegli il comune di residenza dall\'elenco.'];

        $st = db()->prepare('SELECT mo.id, mo.nome, mo.coefficiente, mo.valore_indicativo, ma.nome AS marca, ma.coefficiente AS coeff_marca, ma.tipo_veicolo
            FROM modelli mo JOIN marche ma ON ma.id = mo.marca_id WHERE mo.id = ?' . ($ancheInattivi ? '' : ' AND mo.attivo = 1 AND ma.attivo = 1'));
        $st->execute([$modelloId]);
        $mod = $st->fetch();
        if (!$mod) return [null, 'Scegli marca e modello del veicolo.'];

        $r = Calcolo::stima(self::garanzie(), $scelte, $eta, self::fasce(),
            (float)$comune['coefficiente'], (float)$mod['coeff_marca'], (float)$mod['coefficiente'],
            $mod['valore_indicativo'] !== null ? (float)$mod['valore_indicativo'] : null,
            ['premio_minimo_rc' => (float)impostazione('premio_minimo_rc', '0'), 'arrotondamento' => impostazione('arrotondamento', 'centesimi')]);
        $r['eta'] = $eta;
        $r['comune'] = $comune['nome'] . ' (' . $comune['sigla'] . ')';
        $r['veicolo'] = $mod['marca'] . ' ' . $mod['nome'];
        $r['avviso'] = impostazione('avviso', '');
        return [$r, null];
    }
}
