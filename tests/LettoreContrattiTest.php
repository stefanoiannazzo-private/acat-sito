<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LettoreContrattiTest extends TestCase
{
    private array $comp = [1 => 'Allianz', 2 => 'Generali', 3 => 'Italiana Assicurazioni', 5 => 'AXA'];

    public function testFormulaDalleOreAlleOre(): void
    {
        $t = "ALLIANZ S.p.A. Polizza n. 512345678/AB\nVeicolo targa GH 123 KL\nDurata: dalle ore 24 del 02/01/2026 alle ore 24 del 02/01/2027\nFrazionamento: Semestrale\nPremio annuo lordo € 612,40\nEmessa il 28/12/2025";
        $r = LettoreContratti::analizza($t, $this->comp)['trovati'];
        $this->assertSame('2026-01-02', $r['data_stipula']);
        $this->assertSame('2027-01-02', $r['scadenza']);
        $this->assertSame('semestrale', $r['frazionamento']);
        $this->assertSame('512345678/AB', $r['numero']);
        $this->assertSame(1, $r['compagnia_id']);
        $this->assertSame(612.4, $r['premio_annuo']);
        $this->assertSame('Targa GH123KL', $r['oggetto']);
    }

    public function testEtichetteEMesiInLettere(): void
    {
        $t = "Generali Italia - Polizza Casa\nNumero polizza N° 99-4411-22\nData di effetto 15 marzo 2026\nData di scadenza15/03/2027\nModalità di pagamento: rate mensili\nPremio totale annuo EUR 1.240,00";
        $r = LettoreContratti::analizza($t, $this->comp)['trovati'];
        $this->assertSame('2026-03-15', $r['data_stipula']);
        $this->assertSame('2027-03-15', $r['scadenza']);
        $this->assertSame('mensile', $r['frazionamento']);
        $this->assertSame(2, $r['compagnia_id']);
        $this->assertSame(1240.0, $r['premio_annuo']);
    }

    public function testSoloEffettoPropongoUnAnno(): void
    {
        $r = LettoreContratti::analizza("Contratto AXA, decorrenza 31/01/2026, premio annuo 300,00 euro, polizza annuale senza tacito rinnovo.", $this->comp);
        $this->assertSame('2027-01-31', $r['trovati']['scadenza']);
        $this->assertSame(5, $r['trovati']['compagnia_id']);
        $this->assertNotEmpty($r['note']);
    }

    public function testDocumentoSenzaTesto(): void
    {
        $r = LettoreContratti::analizza('', $this->comp);
        $this->assertSame([], $r['trovati']);
        $this->assertNotEmpty($r['note']);
    }
}
