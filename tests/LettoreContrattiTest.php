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
        $this->assertSame('targa GH123KL', $r['oggetto']);
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

    /** Impaginazione tipica di una polizza auto scaricata da un portale (dati inventati) */
    public function testPolizzaAutoDaPortale(): void
    {
        $t = "Allianz Bonus Malus\nScegli il meglio per la tua polizza auto\nPolizza n. 557000111\nVeicolo assicurato\nassicurato Targa/telaio: AB123CD Data prima immatricolazione:\n31/05/2019\nPRIVATO Marca/modello: BMW 116D 5P. (DAL\n2018/07)\nDurata Decorrenza: dalle ore 24:00 del 31/07/2026 Scadenza:\n31/07/2027\nPremio Premio lordo annuo complessivo: 1.422,77\neuro Periodicità di pagamento: Semestrale Prossima rata:\n31/01/2027\nContratto NUOVO per veicolo precedentemente assicurato presso ITALIANA ASSICURAZIONI S.p.A., con contratto scaduto il 31/07/2026\nAllianz S.p.A. Allianz";
        $r = LettoreContratti::analizza($t, $this->comp)['trovati'];
        $this->assertSame('2026-07-31', $r['data_stipula']);
        $this->assertSame('2027-07-31', $r['scadenza']);
        $this->assertSame('semestrale', $r['frazionamento']);
        $this->assertSame('557000111', $r['numero']);
        $this->assertSame(1, $r['compagnia_id']);
        $this->assertSame(1422.77, $r['premio_annuo']);
        $this->assertSame('BMW 116D 5P · targa AB123CD', $r['oggetto']);
        $this->assertSame('Allianz Bonus Malus', $r['prodotto_nuovo']);
        $this->assertSame('rc_auto', $r['categoria']);
    }

    public function testProdottoDelCatalogo(): void
    {
        $r = LettoreContratti::analizza("Allianz Bonus Malus - polizza auto. Decorrenza 31/07/2026 Scadenza 31/07/2027", $this->comp, [7 => ['Allianz Bonus Malus', 1], 8 => ['Bonus', 1]])['trovati'];
        $this->assertSame(7, $r['prodotto_id']);
    }
}
