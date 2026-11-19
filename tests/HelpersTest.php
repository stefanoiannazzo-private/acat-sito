<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testNumeriAllItaliana(): void
    {
        $this->assertSame(1.25, num('1,25'));
        $this->assertSame(1250.5, num('1.250,50'));
        $this->assertSame(1.25, num('1.25'));
        $this->assertSame(40.0, num('40 %'));
        $this->assertNull(num('abc'));
        $this->assertNull(num(''));
    }

    public function testEta(): void
    {
        $oggi = new DateTimeImmutable('2026-11-18');
        $this->assertSame(18, eta_da_data('2008-11-18', $oggi));
        $this->assertSame(17, eta_da_data('2008-11-19', $oggi));
        $this->assertNull(eta_da_data('2026-12-01', $oggi));
        $this->assertNull(eta_da_data('2001-02-30', $oggi));
        $this->assertNull(eta_da_data('non è una data', $oggi));
    }

    public function testDivisioneSql(): void
    {
        $q = Migrazioni::istruzioni("-- commento\nCREATE TABLE a (x INT);\nINSERT INTO a VALUES (1);\n");
        $this->assertCount(2, $q);
    }

    public function testFileSqlDelProgetto(): void
    {
        foreach (glob(APP_DIR . '/sql/*.sql') as $f) {
            $this->assertNotEmpty(Migrazioni::istruzioni((string)file_get_contents($f)), basename($f));
        }
    }

    public function testNumMigliaiaAllItaliana(): void
    {
        $this->assertSame(100000.0, num('100.000'));
        $this->assertSame(1250000.5, num('1.250.000,50'));
        $this->assertSame(1.5, num('1.5'));
        $this->assertSame(1.5, num('1,5'));
    }
}
