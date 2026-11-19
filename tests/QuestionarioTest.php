<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class QuestionarioTest extends TestCase
{
    private function tcm(array $extra = []): array
    {
        return $extra + [
            'id' => 20, 'nome' => 'Proteggi la tua famiglia', 'compagnia' => 'Alfa', 'tasso' => 1.5, 'capitale_minimo' => 50000, 'capitale_massimo' => 500000,
            'premio_minimo' => null, 'premio_massimo' => null, 'frazionamenti' => ['annuale' => 0.0, 'mensile' => 4.0], 'promozioni' => [],
            'domande' => [
                ['testo' => 'Fumatore?', 'risposte' => [['testo' => 'Sì', 'tipo' => 'percentuale', 'valore' => 80], ['testo' => 'No', 'tipo' => 'percentuale', 'valore' => 0]]],
                ['testo' => 'Durata in anni', 'risposte' => [['testo' => '10', 'tipo' => 'percentuale', 'valore' => 0], ['testo' => '20', 'tipo' => 'importo', 'valore' => 30]]],
            ],
        ];
    }

    public function testBaseETassoPerMille(): void
    {
        [$r, $m] = Calcolo::capitale($this->tcm(), 100000, ['Fumatore?' => 'No', 'Durata in anni' => '10'], ['frazionamento' => 'annuale']);
        $this->assertNull($m);
        $this->assertEqualsWithDelta(150.0, $r['annuo'], 0.001);
        $this->assertCount(1, $r['righe']);
    }

    public function testRisposteAumentanoIlPremio(): void
    {
        [$r] = Calcolo::capitale($this->tcm(), 100000, [' fumatore? ' => 'SÌ', 'durata in anni' => '20'], ['frazionamento' => 'mensile']);
        // 150 + 80% (120) + 30 = 300, poi +4% mensile = 312
        $this->assertEqualsWithDelta(312.0, $r['annuo'], 0.001);
        $this->assertEqualsWithDelta(26.0, $r['rata'], 0.001);
        $this->assertCount(3, $r['righe']);
    }

    public function testRispostaMancanteONonPrevista(): void
    {
        [$r, $m] = Calcolo::capitale($this->tcm(), 100000, ['Fumatore?' => 'No'], ['frazionamento' => 'annuale']);
        $this->assertNull($r);
        $this->assertStringContainsString('Durata', $m);
        [$r, $m] = Calcolo::capitale($this->tcm(), 100000, ['Fumatore?' => 'Forse', 'Durata in anni' => '10'], ['frazionamento' => 'annuale']);
        $this->assertNull($r);
    }

    public function testLimitiDiCapitale(): void
    {
        [$r, $m] = Calcolo::capitale($this->tcm(), 10000, ['Fumatore?' => 'No', 'Durata in anni' => '10'], ['frazionamento' => 'annuale']);
        $this->assertNull($r);
        $this->assertStringContainsString('minimo', $m);
    }
}
