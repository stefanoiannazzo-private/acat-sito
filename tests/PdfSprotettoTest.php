<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** PDF protetti come quelli dei portali delle compagnie: si aprono senza password ma sono cifrati */
final class PdfSprotettoTest extends TestCase
{
    public static function protetti(): array
    {
        return ['RC4 40 bit' => ['enc_40.pdf'], 'RC4 128 bit' => ['enc_128useaesn.pdf'], 'AES 128 bit' => ['enc_128useaesy.pdf'], 'AES 256 bit' => ['enc_256.pdf']];
    }

    /** @dataProvider protetti */
    public function testLeggeIPdfProtetti(string $file): void
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) $this->markTestSkipped('Libreria PDF non installata');
        $t = LettoreContratti::testo(__DIR__ . '/fixtures/' . $file, 'application/pdf');
        $r = LettoreContratti::analizza($t, [1 => 'Allianz'])['trovati'];
        $this->assertSame('2027-01-02', $r['scadenza'] ?? null);
        $this->assertSame('512345678/AB', $r['numero'] ?? null);
        $this->assertSame('semestrale', $r['frazionamento'] ?? null);
    }

    public function testPdfNonProtettoNonViene(): void
    {
        $this->assertNull(PdfSprotetto::decifra((string)file_get_contents(__DIR__ . '/fixtures/semplice.pdf')));
    }

    public function testRc4(): void
    {
        // vettore di prova noto: chiave "Key", testo "Plaintext"
        $this->assertSame('bbf316e8d940af0ad3', bin2hex(PdfSprotetto::rc4('Key', 'Plaintext')));
    }
}
