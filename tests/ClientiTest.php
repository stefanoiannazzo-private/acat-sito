<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ClientiTest extends TestCase
{
    public function testDividiNome(): void
    {
        $this->assertSame(['Mario', 'Rossi'], Clienti::dividiNome('  Mario   Rossi '));
        $this->assertSame(['Anna', 'De Luca Bianchi'], Clienti::dividiNome('Anna De Luca Bianchi'));
        $this->assertSame(['Cher', null], Clienti::dividiNome('Cher'));
    }

    public function testNomeCompletoESaluto(): void
    {
        $this->assertSame('Mario Rossi', Clienti::nomeCompleto('persona', 'Mario', 'Rossi', 'ignorata'));
        $this->assertSame('Rossi Srl', Clienti::nomeCompleto('azienda', '', null, ' Rossi Srl '));
        $this->assertSame('Mario', Clienti::saluto(['tipo' => 'persona', 'nome' => 'Mario Rossi', 'nome_proprio' => 'Mario']));
        $this->assertSame('Gian', Clienti::saluto(['nome' => 'Gian Paolo Verdi']));
        $this->assertSame('Rossi Srl', Clienti::saluto(['tipo' => 'azienda', 'nome' => 'Rossi Srl']));
    }

    public function testSituazionePolizza(): void
    {
        $oggi = '2026-11-19';
        $this->assertSame('attiva', Clienti::situazione('attiva', '2027-03-01', $oggi));
        $this->assertSame('in_scadenza', Clienti::situazione('attiva', '2026-12-10', $oggi));
        $this->assertSame('in_scadenza', Clienti::situazione('attiva', '2026-12-19', $oggi));
        $this->assertSame('attiva', Clienti::situazione('attiva', '2026-12-20', $oggi));
        $this->assertSame('scaduta', Clienti::situazione('attiva', null, $oggi));
        $this->assertSame('scaduta', Clienti::situazione('scaduta', null, $oggi));
        $this->assertSame('chiusa', Clienti::situazione('disdetta', null, $oggi));
    }
}
