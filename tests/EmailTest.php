<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    private function preventivo(): array
    {
        return ['id' => 12, 'veicolo' => 'Fiat Panda', 'comune_nome' => 'Giaveno (TO)', 'compagnia' => 'Allianz', 'decorrenza' => '2026-12-01',
            'valido_fino' => '2026-12-31', 'messaggio' => 'Ecco <il> tuo preventivo', 'totale' => 500, 'totale_definitivo' => 480.5,
            'righe_definitive' => json_encode([['nome' => 'RC', 'importo' => 400.5], ['nome' => 'Cristalli <b>', 'importo' => 80], ['nome' => 'Kasko', 'importo' => null]]),
            'dettaglio' => null];
    }

    public function testModelloPreventivo(): void
    {
        $m = EmailPreventivo::preventivo($this->preventivo(), ['nome' => 'Anna Bianchi'], 'https://esempio.it/il-tuo-preventivo.php?t=abc');
        $this->assertStringContainsString('n. 12', $m['oggetto']);
        $this->assertStringContainsString('Ciao Anna', $m['html']);
        $this->assertStringContainsString('€ 480,50', $m['html']);
        $this->assertStringContainsString('su richiesta', $m['html']);
        $this->assertStringContainsString('31/12/2026', $m['testo']);
        $this->assertStringContainsString('https://esempio.it/il-tuo-preventivo.php?t=abc', $m['testo']);
        // il testo inserito dagli operatori viene sempre neutralizzato
        $this->assertStringNotContainsString('Cristalli <b>', $m['html']);
        $this->assertStringContainsString('Ecco &lt;il&gt; tuo preventivo', $m['html']);
    }

    public function testRigheDaStimaSeMancanoQuelleDefinitive(): void
    {
        $p = ['totale' => 100, 'totale_definitivo' => null, 'righe_definitive' => null, 'dettaglio' => json_encode(['righe' => [['nome' => 'RC', 'importo' => 100, 'spiegazione' => 'x']]])];
        $this->assertSame([['nome' => 'RC', 'importo' => 100]], EmailPreventivo::righe($p));
        $this->assertSame(100.0, EmailPreventivo::totale($p));
    }

    public function testMessaggioMimeSenzaIniezioneDiIntestazioni(): void
    {
        $m = Mailer::componi('noreply@acat.it', "ACAT\r\nBcc: x@evil.it", 'cliente@example.com', "Oggetto\r\nBcc: y@evil.it", '<p>ciao</p>', 'ciao', "r@acat.it\r\nBcc: z@evil.it");
        $this->assertStringNotContainsString("\r\nBcc:", $m['header']);
        $this->assertStringContainsString('multipart/alternative', $m['header']);
        $this->assertStringContainsString(base64_encode('ciao'), str_replace("\r\n", '', $m['body']));
    }
}
