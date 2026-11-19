<?php
declare(strict_types=1);

/** Classificazione delle polizze: aree principali (Auto, Ramo danni, Ramo vita) e categorie. */
final class Categorie
{
    /** @return array<int, array{id:int,nome:string,codice:?string,figli:array}> aree con le loro categorie */
    public static function albero(bool $soloAttive = true): array
    {
        $righe = db()->query('SELECT id, parent_id, chiave, nome, codice, attivo FROM categorie' . ($soloAttive ? ' WHERE attivo = 1' : '') . ' ORDER BY ordine, nome')->fetchAll();
        $aree = [];
        foreach ($righe as $r) if ($r['parent_id'] === null) $aree[(int)$r['id']] = $r + ['figli' => []];
        foreach ($righe as $r) if ($r['parent_id'] !== null && isset($aree[(int)$r['parent_id']])) $aree[(int)$r['parent_id']]['figli'][(int)$r['id']] = $r;
        return $aree;
    }

    /** <option> raggruppate per area; l'area stessa è selezionabile come "generica" */
    public static function opzioni(?int $scelta, bool $includiAree = true, ?int $sempre = null): string
    {
        $h = '';
        foreach (self::albero(false) as $a) {
            if (!$a['attivo'] && (int)$a['id'] !== $sempre) continue;
            $h .= '<optgroup label="' . e($a['nome']) . '">';
            if ($includiAree) $h .= '<option value="' . (int)$a['id'] . '"' . ((int)$a['id'] === $scelta ? ' selected' : '') . '>' . e($a['nome']) . ' (generica)</option>';
            foreach ($a['figli'] as $f) {
                if (!$f['attivo'] && (int)$f['id'] !== $scelta) continue;
                $h .= '<option value="' . (int)$f['id'] . '"' . ((int)$f['id'] === $scelta ? ' selected' : '') . '>' . e($f['nome']) . '</option>';
            }
            $h .= '</optgroup>';
        }
        return $h;
    }

    /** Etichetta "Area · Categoria" */
    public static function etichetta(?int $id): string
    {
        if (!$id) return '–';
        static $cache = null;
        if ($cache === null) $cache = db()->query('SELECT c.id, c.nome, p.nome AS area FROM categorie c LEFT JOIN categorie p ON p.id = c.parent_id')->fetchAll(PDO::FETCH_UNIQUE);
        $c = $cache[$id] ?? null;
        return $c ? ($c['area'] ? $c['area'] . ' · ' . $c['nome'] : $c['nome']) : '–';
    }

    /** Area (Auto, Ramo danni, Ramo vita) di una categoria */
    public static function area(?int $id): ?int
    {
        if (!$id) return null;
        $st = db()->prepare('SELECT COALESCE(parent_id, id) FROM categorie WHERE id = ?');
        $st->execute([$id]);
        return (int)$st->fetchColumn() ?: null;
    }

    public static function perChiave(string $k): ?int
    {
        $st = db()->prepare('SELECT id FROM categorie WHERE chiave = ?');
        $st->execute([$k]);
        return (int)$st->fetchColumn() ?: null;
    }
}
