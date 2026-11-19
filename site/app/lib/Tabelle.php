<?php
declare(strict_types=1);

/**
 * Descrizione delle tabelle gestite dal pannello: campi, elenco, ricerca, filtri e regole.
 * Tipi di campo: testo, testo_lungo, intero, decimale, bool, scelta (opzioni), marca (chiave verso marche).
 */
final class Tabelle
{
    public const RAMI = ['rc_auto' => 'RC auto e moto', 'auto_cvt' => 'Auto: furto, incendio, kasko', 'casa' => 'Casa', 'famiglia' => 'Famiglia e RC privata',
        'infortuni' => 'Infortuni', 'salute' => 'Salute', 'vita' => 'Vita', 'risparmio' => 'Risparmio e previdenza', 'impresa' => 'Impresa e negozi',
        'professionisti' => 'Professionisti', 'viaggi' => 'Viaggi', 'altro' => 'Altro'];

    public static function tutte(): array
    {
        return [
            'garanzie' => [
                'titolo' => 'Garanzie', 'singolare' => 'garanzia', 'ordine' => 'principale DESC, ordine, nome',
                'ricerca' => ['nome', 'codice'], 'filtri' => ['attivo'],
                'lista' => ['ordine', 'codice', 'nome', 'tipo_calcolo', 'valore', 'applica_coefficienti', 'attivo'],
                'intro' => 'L\'RC è la garanzia principale: è sempre inclusa e non si può eliminare. Ogni altra garanzia, comprese quelle nuove, è legata all\'RC e si aggiunge solo insieme a lei.',
                'campi' => [
                    'codice' => ['label' => 'Codice', 'tipo' => 'testo', 'obbl' => true, 'max' => 30, 'pattern' => '/^[A-Z0-9_]+$/', 'aiuto' => 'Lettere maiuscole, numeri e _ (es. ASSISTENZA)'],
                    'nome' => ['label' => 'Nome', 'tipo' => 'testo', 'obbl' => true, 'max' => 80],
                    'descrizione' => ['label' => 'Descrizione per il cliente', 'tipo' => 'testo', 'max' => 255],
                    'tipo_calcolo' => ['label' => 'Calcolo', 'tipo' => 'scelta', 'obbl' => true, 'opzioni' => ['fisso' => 'Importo fisso (€)', 'perc_rc' => '% dell\'RC', 'perc_valore' => '% del valore del veicolo']],
                    'valore' => ['label' => 'Valore', 'tipo' => 'decimale', 'obbl' => true, 'min' => 0, 'aiuto' => 'Per l\'RC: il premio base in euro. Per le altre: euro o percentuale secondo il calcolo scelto.'],
                    'applica_coefficienti' => ['label' => 'Applica i coefficienti di comune e veicolo', 'breve' => 'Coefficienti', 'tipo' => 'bool', 'aiuto' => 'Solo per gli importi fissi. L\'RC li applica sempre.'],
                    'ordine' => ['label' => 'Ordine', 'tipo' => 'intero', 'min' => 0, 'max_val' => 9999, 'predefinito' => 100],
                    'attivo' => ['label' => 'Attiva', 'tipo' => 'bool', 'predefinito' => 1],
                ],
            ],
            'compagnie' => [
                'titolo' => 'Compagnie', 'singolare' => 'compagnia', 'ordine' => 'nome',
                'ricerca' => ['nome', 'codice_agenzia'], 'filtri' => ['attivo'],
                'lista' => ['nome', 'n_prodotti', 'n_polizze', 'telefono', 'attivo'], 'csv' => false,
                'calcolati' => [
                    'n_prodotti' => ['label' => 'Prodotti', 'sql' => '(SELECT COUNT(*) FROM prodotti pr WHERE pr.compagnia_id = t.id)'],
                    'n_polizze' => ['label' => 'Polizze attive', 'sql' => "(SELECT COUNT(*) FROM polizze po WHERE po.compagnia_id = t.id AND po.stato = 'attiva')"],
                ],
                'intro' => 'Le compagnie con cui lavori. Una compagnia con prodotti o polizze collegate non si può eliminare: disattivala.',
                'campi' => [
                    'nome' => ['label' => 'Nome', 'tipo' => 'testo', 'obbl' => true, 'max' => 80],
                    'portale_url' => ['label' => 'Indirizzo del portale', 'tipo' => 'testo', 'max' => 255, 'pattern' => '#^https?://\S+$#', 'aiuto' => 'Es. https://portale.compagnia.it: compare come collegamento nelle polizze'],
                    'codice_agenzia' => ['label' => 'Codice agenzia / intermediario', 'tipo' => 'testo', 'max' => 60],
                    'telefono' => ['label' => 'Telefono assistenza', 'tipo' => 'testo', 'max' => 40],
                    'email' => ['label' => 'Email di riferimento', 'tipo' => 'testo', 'max' => 190],
                    'note' => ['label' => 'Note', 'tipo' => 'testo_lungo'],
                    'attivo' => ['label' => 'Attiva', 'tipo' => 'bool', 'predefinito' => 1],
                ],
            ],
            'prodotti' => [
                'titolo' => 'Prodotti', 'singolare' => 'prodotto', 'ordine' => 'compagnia_nome, nome',
                'ricerca' => ['nome', 'descrizione'], 'filtri' => ['compagnia_id', 'ramo', 'attivo'],
                'lista' => ['compagnia_nome', 'nome', 'ramo', 'n_polizze', 'attivo'], 'csv' => true,
                'calcolati' => [
                    'compagnia_nome' => ['label' => 'Compagnia', 'sql' => '(SELECT c.nome FROM compagnie c WHERE c.id = t.compagnia_id)'],
                    'n_polizze' => ['label' => 'Polizze attive', 'sql' => "(SELECT COUNT(*) FROM polizze po WHERE po.prodotto_id = t.id AND po.stato = 'attiva')"],
                ],
                'intro' => 'Il catalogo di ogni compagnia. Nella polizza di un cliente scegli prima la compagnia, poi uno dei suoi prodotti.',
                'campi' => [
                    'compagnia_id' => ['label' => 'Compagnia', 'tipo' => 'compagnia', 'obbl' => true],
                    'nome' => ['label' => 'Nome del prodotto', 'tipo' => 'testo', 'obbl' => true, 'max' => 120],
                    'ramo' => ['label' => 'Ramo', 'tipo' => 'scelta', 'obbl' => true, 'opzioni' => self::RAMI],
                    'descrizione' => ['label' => 'Descrizione', 'tipo' => 'testo', 'max' => 255],
                    'attivo' => ['label' => 'Attivo', 'tipo' => 'bool', 'predefinito' => 1],
                ],
            ],
            'stati_cliente' => [
                'titolo' => 'Stati dei clienti', 'singolare' => 'stato', 'ordine' => 'ordine, nome',
                'ricerca' => ['nome'], 'filtri' => ['attivo'],
                'lista' => ['ordine', 'nome', 'colore', 'sistema', 'attivo'],
                'intro' => 'Gli stati che scegli nella scheda del cliente. Quelli "automatici" vengono assegnati dal sistema (nuova richiesta dal sito, preventivo inviato, preventivo accettato): puoi rinominarli ma non eliminarli.',
                'campi' => [
                    'nome' => ['label' => 'Nome', 'tipo' => 'testo', 'obbl' => true, 'max' => 60],
                    'colore' => ['label' => 'Colore', 'tipo' => 'testo', 'obbl' => true, 'max' => 7, 'pattern' => '/^#[0-9a-fA-F]{6}$/', 'predefinito' => '#5a6880', 'aiuto' => 'Codice esadecimale, es. #1f7a4d (verde), #b3302a (rosso), #2563a8 (blu)'],
                    'ordine' => ['label' => 'Ordine', 'tipo' => 'intero', 'min' => 0, 'max_val' => 9999, 'predefinito' => 100],
                    'sistema' => ['label' => 'Automatico', 'tipo' => 'bool', 'sola_lettura' => true],
                    'attivo' => ['label' => 'Attivo', 'tipo' => 'bool', 'predefinito' => 1],
                ],
            ],
            'fasce_eta' => [
                'titolo' => 'Fasce d\'età', 'singolare' => 'fascia d\'età', 'ordine' => 'eta_min',
                'ricerca' => ['descrizione'], 'filtri' => ['attivo'],
                'lista' => ['eta_min', 'eta_max', 'tipo', 'valore', 'descrizione', 'attivo'],
                'intro' => 'Il supplemento si applica all\'RC in base all\'età del contraente. Le fasce attive non devono sovrapporsi.',
                'campi' => [
                    'eta_min' => ['label' => 'Da anni', 'tipo' => 'intero', 'obbl' => true, 'min' => 14, 'max_val' => 120],
                    'eta_max' => ['label' => 'A anni (compreso)', 'tipo' => 'intero', 'obbl' => true, 'min' => 14, 'max_val' => 120],
                    'tipo' => ['label' => 'Supplemento', 'tipo' => 'scelta', 'obbl' => true, 'opzioni' => ['percentuale' => 'Percentuale sull\'RC', 'importo' => 'Importo fisso (€)']],
                    'valore' => ['label' => 'Valore', 'tipo' => 'decimale', 'obbl' => true, 'aiuto' => '0 = nessun supplemento; un valore negativo è uno sconto'],
                    'descrizione' => ['label' => 'Descrizione', 'tipo' => 'testo', 'max' => 120],
                    'attivo' => ['label' => 'Attiva', 'tipo' => 'bool', 'predefinito' => 1],
                ],
            ],
            'comuni' => [
                'titolo' => 'Comuni', 'singolare' => 'comune', 'ordine' => 'nome',
                'ricerca' => ['nome', 'codice_istat', 'provincia', 'sigla'], 'filtri' => ['sigla', 'regione', 'attivo'],
                'lista' => ['nome', 'sigla', 'regione', 'coefficiente', 'attivo'], 'massivo' => true, 'csv' => true,
                'intro' => 'Il coefficiente moltiplica l\'RC: 1 = nessuna variazione, 1,15 = +15%, 0,90 = -10%. Filtra per provincia o regione e usa "Modifica tutti i filtrati" per cambiarli insieme.',
                'campi' => [
                    'codice_istat' => ['label' => 'Codice ISTAT', 'tipo' => 'testo', 'obbl' => true, 'max' => 6, 'pattern' => '/^\d{6}$/'],
                    'nome' => ['label' => 'Comune', 'tipo' => 'testo', 'obbl' => true, 'max' => 100],
                    'sigla' => ['label' => 'Provincia (sigla)', 'tipo' => 'testo', 'obbl' => true, 'max' => 2, 'pattern' => '/^[A-Z]{2}$/'],
                    'provincia' => ['label' => 'Provincia', 'tipo' => 'testo', 'obbl' => true, 'max' => 60],
                    'regione' => ['label' => 'Regione', 'tipo' => 'testo', 'obbl' => true, 'max' => 60],
                    'cap' => ['label' => 'CAP', 'tipo' => 'testo', 'max' => 5, 'pattern' => '/^\d{5}$/'],
                    'coefficiente' => ['label' => 'Coefficiente', 'tipo' => 'decimale', 'obbl' => true, 'min' => 0.001, 'predefinito' => 1],
                    'attivo' => ['label' => 'Attivo', 'tipo' => 'bool', 'predefinito' => 1],
                ],
            ],
            'marche' => [
                'titolo' => 'Marche', 'singolare' => 'marca', 'ordine' => 'tipo_veicolo, nome',
                'ricerca' => ['nome'], 'filtri' => ['tipo_veicolo', 'attivo'],
                'lista' => ['nome', 'tipo_veicolo', 'coefficiente', 'n_modelli', 'attivo'], 'massivo' => true, 'csv' => true,
                'calcolati' => ['n_modelli' => ['label' => 'Modelli', 'sql' => '(SELECT COUNT(*) FROM modelli mo WHERE mo.marca_id = t.id)']],
                'intro' => 'Il coefficiente della marca si moltiplica con quello del modello. Le marche non attive non compaiono nel preventivatore.',
                'campi' => [
                    'nome' => ['label' => 'Marca', 'tipo' => 'testo', 'obbl' => true, 'max' => 80],
                    'tipo_veicolo' => ['label' => 'Veicolo', 'tipo' => 'scelta', 'obbl' => true, 'opzioni' => ['auto' => 'Auto', 'moto' => 'Moto e scooter']],
                    'coefficiente' => ['label' => 'Coefficiente', 'tipo' => 'decimale', 'obbl' => true, 'min' => 0.001, 'predefinito' => 1],
                    'attivo' => ['label' => 'Attiva', 'tipo' => 'bool', 'predefinito' => 1],
                ],
            ],
            'modelli' => [
                'titolo' => 'Modelli', 'singolare' => 'modello', 'ordine' => 'marca_nome, nome',
                'ricerca' => ['nome'], 'filtri' => ['marca_id', 'attivo'],
                'lista' => ['marca_nome', 'nome', 'anno_inizio', 'anno_fine', 'coefficiente', 'valore_indicativo', 'attivo'], 'massivo' => true, 'csv' => true,
                'calcolati' => ['marca_nome' => ['label' => 'Marca', 'sql' => '(SELECT CONCAT(ma.nome, IF(ma.tipo_veicolo = \'moto\', \' (moto)\', \'\')) FROM marche ma WHERE ma.id = t.marca_id)']],
                'intro' => 'Il valore indicativo serve solo alle garanzie calcolate in percentuale sul valore del veicolo.',
                'campi' => [
                    'marca_id' => ['label' => 'Marca', 'tipo' => 'marca', 'obbl' => true],
                    'nome' => ['label' => 'Modello', 'tipo' => 'testo', 'obbl' => true, 'max' => 120],
                    'anno_inizio' => ['label' => 'In produzione dal', 'tipo' => 'intero', 'min' => 1900, 'max_val' => 2100],
                    'anno_fine' => ['label' => 'Fino al', 'tipo' => 'intero', 'min' => 1900, 'max_val' => 2100, 'aiuto' => 'Vuoto = ancora in produzione'],
                    'alimentazione' => ['label' => 'Alimentazione', 'tipo' => 'testo', 'max' => 30],
                    'coefficiente' => ['label' => 'Coefficiente', 'tipo' => 'decimale', 'obbl' => true, 'min' => 0.001, 'predefinito' => 1],
                    'valore_indicativo' => ['label' => 'Valore indicativo (€)', 'tipo' => 'decimale', 'min' => 0],
                    'attivo' => ['label' => 'Attivo', 'tipo' => 'bool', 'predefinito' => 1],
                ],
            ],
        ];
    }

    public static function def(string $t): ?array
    {
        return self::tutte()[$t] ?? null;
    }

    /** Converte e controlla i valori inviati dal modulo. @return array{0:array,1:array} [valori, errori] */
    public static function leggi(array $def, array $post): array
    {
        $v = []; $err = [];
        foreach ($def['campi'] as $k => $c) {
            if (!empty($c['sola_lettura'])) continue;
            $raw = $post[$k] ?? null;
            $lab = $c['label'];
            switch ($c['tipo']) {
                case 'bool':
                    $v[$k] = !empty($raw) ? 1 : 0;
                    continue 2;
                case 'intero':
                    $raw = trim((string)$raw);
                    if ($raw === '') { $v[$k] = null; break; }
                    if (!preg_match('/^-?\d+$/', $raw)) { $err[$k] = "$lab: serve un numero intero."; break; }
                    $v[$k] = (int)$raw;
                    break;
                case 'decimale':
                    $raw = trim((string)$raw);
                    if ($raw === '') { $v[$k] = null; break; }
                    $n = num($raw);
                    if ($n === null) { $err[$k] = "$lab: serve un numero (es. 1,25)."; break; }
                    $v[$k] = $n;
                    break;
                case 'marca':
                case 'compagnia':
                    $v[$k] = (int)$raw ?: null;
                    break;
                case 'testo_lungo':
                    $s = trim((string)$raw);
                    $v[$k] = $s === '' ? null : mb_substr($s, 0, 5000);
                    break;
                case 'scelta':
                    $raw = (string)$raw;
                    $v[$k] = array_key_exists($raw, $c['opzioni']) ? $raw : null;
                    break;
                default:
                    $s = trim((string)$raw);
                    if (isset($c['pattern']) && in_array($k, ['codice', 'sigla'], true)) $s = mb_strtoupper($s);
                    $v[$k] = $s === '' ? null : $s;
                    if ($s !== '' && isset($c['max']) && mb_strlen($s) > $c['max']) $err[$k] = "$lab: al massimo {$c['max']} caratteri.";
                    if ($s !== '' && isset($c['pattern']) && !preg_match($c['pattern'], $s)) $err[$k] = "$lab: formato non valido.";
            }
            if (isset($err[$k])) continue;
            if (!empty($c['obbl']) && ($v[$k] === null || $v[$k] === '')) { $err[$k] = "$lab: campo obbligatorio."; continue; }
            if ($v[$k] !== null && is_numeric($v[$k])) {
                if (isset($c['min']) && $v[$k] < $c['min']) $err[$k] = "$lab: almeno " . str_replace('.', ',', (string)$c['min']) . '.';
                if (isset($c['max_val']) && $v[$k] > $c['max_val']) $err[$k] = "$lab: al massimo {$c['max_val']}.";
            }
        }
        return [$v, $err];
    }
}
