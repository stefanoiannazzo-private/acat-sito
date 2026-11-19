<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ListinoTest extends TestCase
{
    private function prodotto(array $extra = []): array
    {
        return $extra + [
            'id' => 10, 'nome' => 'Auto Base', 'compagnia' => 'Alfa', 'veicoli' => 'auto', 'premio_minimo' => null, 'premio_massimo' => null,
            'garanzie' => [
                ['id' => 1, 'codice' => 'RC', 'nome' => 'RC', 'principale' => 1, 'dipende_da' => null, 'tipo_calcolo' => 'fisso', 'valore' => 400, 'applica_coefficienti' => 1, 'attivo' => 1],
                ['id' => 2, 'codice' => 'CRISTALLI', 'nome' => 'Cristalli', 'principale' => 0, 'dipende_da' => 1, 'tipo_calcolo' => 'fisso', 'valore' => 60, 'applica_coefficienti' => 0, 'attivo' => 1],
                ['id' => 3, 'codice' => 'FURTO', 'nome' => 'Furto', 'principale' => 0, 'dipende_da' => 1, 'tipo_calcolo' => 'perc_rc', 'valore' => 10, 'applica_coefficienti' => 0, 'attivo' => 1],
            ],
            'frazionamenti' => ['annuale' => 0.0, 'semestrale' => 3.0, 'mensile' => 5.0],
            'durate' => [1 => 0.0, 2 => 5.0],
            'promozioni' => [], 'correzioni' => [],
        ];
    }

    private function ctx(array $extra = []): array
    {
        return $extra + ['coeff_comune' => 1.0, 'coeff_marca' => 1.0, 'coeff_modello' => 1.0, 'valore' => null, 'sigla' => 'TO', 'regione' => 'Piemonte',
            'marca_id' => 7, 'tipo_veicolo' => 'auto', 'frazionamento' => 'annuale', 'durata' => 1, 'oggi' => '2026-11-19'];
    }

    public function testAnnualeSenzaOpzioni(): void
    {
        [$r, $m] = Calcolo::prodotto($this->prodotto(), ['CRISTALLI'], 40, [], $this->ctx());
        $this->assertNull($m);
        $this->assertEqualsWithDelta(460.0, $r['annuo'], 0.001);
        $this->assertSame(1, $r['rate']);
    }

    public function testFrazionamentoMensileMaggiora(): void
    {
        [$r] = Calcolo::prodotto($this->prodotto(), [], 40, [], $this->ctx(['frazionamento' => 'mensile']));
        $this->assertEqualsWithDelta(420.0, $r['annuo'], 0.001); // 400 + 5%
        $this->assertEqualsWithDelta(35.0, $r['rata'], 0.001);
    }

    public function testFrazionamentoNonPrevistoEscludeIlProdotto(): void
    {
        [$r, $m] = Calcolo::prodotto($this->prodotto(), [], 40, [], $this->ctx(['frazionamento' => 'trimestrale']));
        $this->assertNull($r);
        $this->assertStringContainsString('trimestrale', $m);
    }

    public function testGaranziaMancanteEscludeIlProdotto(): void
    {
        [$r, $m] = Calcolo::prodotto($this->prodotto(), ['KASKO'], 40, [], $this->ctx());
        $this->assertNull($r);
        $this->assertStringContainsString('KASKO', $m);
    }

    public function testCorrezioneProvinciaMoltiplicaRcEPercentuali(): void
    {
        $p = $this->prodotto(['correzioni' => [['tipo' => 'provincia', 'eta_min' => null, 'eta_max' => null, 'chiave' => 'TO', 'moltiplicatore' => 1.1, 'descrizione' => 'Torino']]]);
        [$r] = Calcolo::prodotto($p, ['FURTO'], 40, [], $this->ctx());
        $this->assertEqualsWithDelta(484.0, $r['annuo'], 0.001); // RC 440 + furto 10% = 44
        $this->assertSame(['Torino ×1,1'], $r['correzioni']);
    }

    public function testCorrezioneEtaFuoriFasciaNonSiApplica(): void
    {
        $p = $this->prodotto(['correzioni' => [['tipo' => 'eta', 'eta_min' => 18, 'eta_max' => 25, 'chiave' => null, 'moltiplicatore' => 1.5, 'descrizione' => '']]]);
        [$r] = Calcolo::prodotto($p, [], 40, [], $this->ctx());
        $this->assertEqualsWithDelta(400.0, $r['annuo'], 0.001);
    }

    public function testPromozioneAttivaEScaduta(): void
    {
        $p = $this->prodotto(['promozioni' => [
            ['nome' => 'Autunno', 'tipo' => 'percentuale', 'valore' => 10, 'dal' => '2026-11-01', 'al' => '2026-11-30', 'attivo' => 1],
            ['nome' => 'Estate', 'tipo' => 'importo', 'valore' => 50, 'dal' => '2026-06-01', 'al' => '2026-08-31', 'attivo' => 1],
        ]]);
        [$r] = Calcolo::prodotto($p, [], 40, [], $this->ctx());
        $this->assertEqualsWithDelta(360.0, $r['annuo'], 0.001);
        $this->assertCount(1, $r['sconti']);
    }

    public function testDurataPluriennale(): void
    {
        [$r] = Calcolo::prodotto($this->prodotto(), [], 40, [], $this->ctx(['durata' => 2]));
        $this->assertEqualsWithDelta(380.0, $r['annuo'], 0.001);
        $this->assertEqualsWithDelta(760.0, $r['totale_durata'], 0.001);
        [$r, $m] = Calcolo::prodotto($this->prodotto(), [], 40, [], $this->ctx(['durata' => 3]));
        $this->assertNull($r);
    }

    public function testPremioMinimoEMassimo(): void
    {
        [$r] = Calcolo::prodotto($this->prodotto(['premio_minimo' => 450]), [], 40, [], $this->ctx());
        $this->assertEqualsWithDelta(450.0, $r['annuo'], 0.001);
        [$r] = Calcolo::prodotto($this->prodotto(['premio_massimo' => 300]), [], 40, [], $this->ctx(['frazionamento' => 'semestrale']));
        $this->assertEqualsWithDelta(309.0, $r['annuo'], 0.001); // massimo 300, poi +3% semestrale
        $this->assertEqualsWithDelta(154.5, $r['rata'], 0.001);
    }

    public function testVeicoloNonAssicurato(): void
    {
        [$r] = Calcolo::prodotto($this->prodotto(), [], 40, [], $this->ctx(['tipo_veicolo' => 'moto']));
        $this->assertNull($r);
    }
}
