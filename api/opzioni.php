<?php
/* Elenchi per il modulo pubblico: impostazioni, comuni (ricerca), marche, modelli, garanzie. */
declare(strict_types=1);
require __DIR__ . '/_init.php';

if (!Limiti::consenti('opzioni', 240)) json_out(['ok' => false, 'errore' => 'Troppe richieste, riprova tra un minuto.'], 429);
$cosa = (string)($_GET['cosa'] ?? '');

switch ($cosa) {
    case 'config':
        json_out(['ok' => true, 'attivo' => impostazione('preventivatore_attivo', '1') === '1', 'mostra_prezzo' => impostazione('mostra_prezzo', 'subito'),
            'eta_minima' => (int)impostazione('eta_minima', '18'), 'avviso' => impostazione('avviso', '')]);
    case 'comuni':
        $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 60));
        if (mb_strlen($q) < 2) json_out(['ok' => true, 'comuni' => []]);
        $st = db()->prepare("SELECT id, nome, sigla FROM comuni WHERE attivo = 1 AND nome LIKE ? ORDER BY (nome LIKE ?) DESC, nome LIMIT 15");
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $st->execute([$like . '%', $like]);
        $r = $st->fetchAll();
        if (count($r) < 15) {
            $st = db()->prepare("SELECT id, nome, sigla FROM comuni WHERE attivo = 1 AND nome LIKE ? AND nome NOT LIKE ? ORDER BY nome LIMIT " . (15 - count($r)));
            $st->execute(['%' . $like . '%', $like . '%']);
            $r = array_merge($r, $st->fetchAll());
        }
        json_out(['ok' => true, 'comuni' => array_map(fn($c) => ['id' => (int)$c['id'], 'nome' => $c['nome'] . ' (' . $c['sigla'] . ')'], $r)]);
    case 'marche':
        $tipo = ($_GET['tipo'] ?? 'auto') === 'moto' ? 'moto' : 'auto';
        $st = db()->prepare('SELECT ma.id, ma.nome FROM marche ma WHERE ma.attivo = 1 AND ma.tipo_veicolo = ? AND EXISTS (SELECT 1 FROM modelli mo WHERE mo.marca_id = ma.id AND mo.attivo = 1) ORDER BY ma.nome');
        $st->execute([$tipo]);
        json_out(['ok' => true, 'marche' => array_map(fn($m) => ['id' => (int)$m['id'], 'nome' => $m['nome']], $st->fetchAll())]);
    case 'modelli':
        $st = db()->prepare('SELECT mo.id, mo.nome, mo.anno_inizio, mo.anno_fine FROM modelli mo JOIN marche ma ON ma.id = mo.marca_id WHERE mo.marca_id = ? AND mo.attivo = 1 AND ma.attivo = 1 ORDER BY mo.nome');
        $st->execute([(int)($_GET['marca'] ?? 0)]);
        json_out(['ok' => true, 'modelli' => array_map(fn($m) => ['id' => (int)$m['id'], 'nome' => $m['nome']], $st->fetchAll())]);
    case 'garanzie':
        json_out(['ok' => true, 'garanzie' => Preventivatore::garanziePubbliche()] + Preventivatore::opzioniPagamento());
    case 'tipi_polizza':
        json_out(['ok' => true, 'tipi' => Preventivatore::categorieCapitale(true)]);
    case 'questionario':
        $l = Preventivatore::prodottiCapitale((int)($_GET['categoria'] ?? 0), true);
        if (!$l) json_out(['ok' => false, 'errore' => 'Tipo di polizza non disponibile online.'], 404);
        json_out(['ok' => true] + Preventivatore::questionario($l));
    default:
        json_out(['ok' => false, 'errore' => 'Richiesta non valida.'], 400);
}
