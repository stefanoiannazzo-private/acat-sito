<?php
declare(strict_types=1);

/**
 * Calcoli sulle scadenze delle polizze (funzioni pure, verificate dai test).
 * "scadenza" è la fine dell'annualità in corso; le rate dividono l'annualità
 * in parti uguali che terminano tutte prima della scadenza (l'ultima coincide con lei).
 */
final class Scadenze
{
    public const MESI = ['annuale' => 12, 'semestrale' => 6, 'trimestrale' => 3, 'mensile' => 1];
    public const NOMI = ['annuale' => 'Annuale', 'semestrale' => 'Semestrale', 'trimestrale' => 'Trimestrale', 'mensile' => 'Mensile'];
    public const STATI = ['attiva' => 'Attiva', 'disdetta' => 'Disdetta', 'sostituita' => 'Sostituita', 'scaduta' => 'Scaduta'];

    /** Aggiunge mesi senza "sforare" (31 gennaio + 1 mese = 28/29 febbraio) */
    public static function aggiungiMesi(DateTimeImmutable $d, int $mesi): DateTimeImmutable
    {
        $y = (int)$d->format('Y'); $m = (int)$d->format('n') + $mesi; $g = (int)$d->format('j');
        $y += intdiv($m - 1, 12) - ($m - 1 < 0 && ($m - 1) % 12 !== 0 ? 1 : 0);
        $m = (($m - 1) % 12 + 12) % 12 + 1;
        $ultimo = (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t');
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, min($g, $ultimo)));
    }

    /** Tutte le scadenze delle rate dell'annualità che termina in $scadenza (l'ultima è la scadenza stessa) */
    public static function rate(string $scadenza, string $frazionamento): array
    {
        $m = self::MESI[$frazionamento] ?? 12;
        $fine = new DateTimeImmutable($scadenza);
        $out = [];
        for ($k = 12 / $m - 1; $k >= 0; $k--) $out[] = self::aggiungiMesi($fine, -$k * $m)->format('Y-m-d');
        return $out;
    }

    /** Prossima scadenza (rata o annualità) dal giorno indicato in poi; null se l'annualità è già passata */
    public static function prossima(string $scadenza, string $frazionamento, ?string $oggi = null): ?array
    {
        $oggi ??= date('Y-m-d');
        foreach (self::rate($scadenza, $frazionamento) as $d) {
            if ($d >= $oggi) return ['data' => $d, 'evento' => $d === $scadenza ? 'annualita' : 'rata'];
        }
        return null;
    }

    /** Giorno in cui parte l'avviso per una scadenza */
    public static function dataAvviso(string $data, int $giorni): string
    {
        return (new DateTimeImmutable($data))->modify('-' . max(0, $giorni) . ' days')->format('Y-m-d');
    }

    /** Durata tipica: la scadenza proposta è un anno dopo la data di effetto/stipula */
    public static function scadenzaDaStipula(string $stipula): string
    {
        return self::aggiungiMesi(new DateTimeImmutable($stipula), 12)->format('Y-m-d');
    }

    /**
     * Avvisi da inviare adesso per una polizza attiva.
     * @param array $p      polizza: scadenza, frazionamento, avvisa_cliente
     * @param array $cfg    giorni_agenzia, giorni_cliente, ora, rate (bool), cliente (bool: invio anche al cliente)
     * @return array<array{evento:string,data:string,destinatario:string}>
     */
    public static function daInviare(array $p, array $cfg, DateTimeImmutable $adesso): array
    {
        $oggi = $adesso->format('Y-m-d');
        if ((int)$adesso->format('G') < (int)$cfg['ora']) return [];
        $out = [];
        foreach (self::rate($p['scadenza'], $p['frazionamento']) as $d) {
            if ($d < $oggi) continue; // già passata
            $evento = $d === $p['scadenza'] ? 'annualita' : 'rata';
            if ($evento === 'rata' && empty($cfg['rate'])) continue;
            if (self::dataAvviso($d, (int)$cfg['giorni_agenzia']) <= $oggi) $out[] = ['evento' => $evento, 'data' => $d, 'destinatario' => 'agenzia'];
            if (!empty($cfg['cliente']) && (int)$p['avvisa_cliente'] === 1 && self::dataAvviso($d, (int)$cfg['giorni_cliente']) <= $oggi) {
                $out[] = ['evento' => $evento, 'data' => $d, 'destinatario' => 'cliente'];
            }
        }
        return $out;
    }

    /** Sostituisce i segnaposto {nome} nei testi degli avvisi */
    public static function compila(string $testo, array $valori): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn($m) => array_key_exists($m[1], $valori) ? (string)$valori[$m[1]] : $m[0], $testo);
    }
}
