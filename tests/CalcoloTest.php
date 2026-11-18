<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CalcoloTest extends TestCase
{
    private function garanzie(): array
    {
        return [
            ['id' => 1, 'codice' => 'RC', 'nome' => 'RC', 'principale' => 1, 'dipende_da' => null, 'tipo_calcolo' => 'fisso', 'valore' => 400, 'applica_coefficienti' => 1],
            ['id' => 2, 'codice' => 'CRISTALLI', 'nome' => 'Cristalli', 'principale' => 0, 'dipende_da' => 1, 'tipo_calcolo' => 'fisso', 'valore' => 60, 'applica_coefficienti' => 0],
            ['id' => 3, 'codice' => 'FURTO', 'nome' => 'Furto', 'principale' => 0, 'dipende_da' => 1, 'tipo_calcolo' => 'perc_rc', 'valore' => 15, 'applica_coefficienti' => 0],
            ['id' => 4, 'codice' => 'KASKO', 'nome' => 'Kasko', 'principale' => 0, 'dipende_da' => 1, 'tipo_calcolo' => 'perc_valore', 'valore' => 3, 'applica_coefficienti' => 0],
            ['id' => 5, 'codice' => 'VAND', 'nome' => 'Atti vandalici', 'principale' => 0, 'dipende_da' => 1, 'tipo_calcolo' => 'fisso', 'valore' => 50, 'applica_coefficienti' => 1],
        ];
    }

    private function fasce(): array
    {
        return [
            ['eta_min' => 18, 'eta_max' => 25, 'tipo' => 'percentuale', 'valore' => 30, 'descrizione' => 'Giovani'],
            ['eta_min' => 26, 'eta_max' => 75, 'tipo' => 'percentuale', 'valore' => 0, 'descrizione' => 'Standard'],
            ['eta_min' => 76, 'eta_max' => 120, 'tipo' => 'importo', 'valore' => 80, 'descrizione' => 'Over 75'],
        ];
    }

    public function testSoloRcConCoefficienti(): void
    {
        $r = Calcolo::stima($this->garanzie(), [], 40, $this->fasce(), 1.2, 1.0, 1.1, null);
        $this->assertCount(1, $r['righe']);
        $this->assertEqualsWithDelta(528.0, $r['totale'], 0.001); // 400 × 1,2 × 1,1
    }

    public function testSupplementoPercentualeGiovani(): void
    {
        $r = Calcolo::stima($this->garanzie(), [], 20, $this->fasce(), 1.0, 1.0, 1.0, null);
        $this->assertEqualsWithDelta(520.0, $r['totale'], 0.001); // 400 + 30%
        $this->assertSame('Giovani', $r['fascia_eta']);
    }

    public function testSupplementoAImporto(): void
    {
        $r = Calcolo::stima($this->garanzie(), [], 80, $this->fasce(), 1.0, 1.0, 1.0, null);
        $this->assertEqualsWithDelta(480.0, $r['totale'], 0.001);
    }

    public function testAccessorieDeiTreTipi(): void
    {
        $r = Calcolo::stima($this->garanzie(), [2, 3, 4, 5], 40, $this->fasce(), 2.0, 1.0, 1.0, 20000);
        // RC 800; cristalli 60; furto 15% di 800 = 120; kasko 3% di 20.000 = 600; vandalici 50 × 2 = 100
        $this->assertEqualsWithDelta(800 + 60 + 120 + 600 + 100, $r['totale'], 0.001);
        $this->assertFalse($r['su_richiesta']);
    }

    public function testPercentualeSulValoreSenzaValoreESuRichiesta(): void
    {
        $r = Calcolo::stima($this->garanzie(), [4], 40, $this->fasce(), 1.0, 1.0, 1.0, null);
        $this->assertTrue($r['su_richiesta']);
        $this->assertNull($r['righe'][1]['importo']);
        $this->assertEqualsWithDelta(400.0, $r['totale'], 0.001);
    }

    public function testPremioMinimo(): void
    {
        $r = Calcolo::stima($this->garanzie(), [], 40, $this->fasce(), 0.5, 1.0, 1.0, null, ['premio_minimo_rc' => 250]);
        $this->assertEqualsWithDelta(250.0, $r['totale'], 0.001);
    }

    public function testArrotondamentoAllEuro(): void
    {
        $r = Calcolo::stima($this->garanzie(), [], 40, $this->fasce(), 1.0013, 1.0, 1.0, null, ['arrotondamento' => 'euro']);
        $this->assertSame(401.0, $r['totale']);
    }

    public function testGaranziaNonLegataAllRcVieneIgnorata(): void
    {
        $g = $this->garanzie();
        $g[] = ['id' => 9, 'codice' => 'X', 'nome' => 'Sciolta', 'principale' => 0, 'dipende_da' => 99, 'tipo_calcolo' => 'fisso', 'valore' => 1000, 'applica_coefficienti' => 0];
        $r = Calcolo::stima($g, [9], 40, $this->fasce(), 1.0, 1.0, 1.0, null);
        $this->assertCount(1, $r['righe']);
    }

    public function testSenzaRcErrore(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Calcolo::stima(array_slice($this->garanzie(), 1), [], 40, $this->fasce(), 1, 1, 1, null);
    }
}
