<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ScadenzeTest extends TestCase
{
    public function testMesiSenzaSforare(): void
    {
        $d = fn($s, $m) => Scadenze::aggiungiMesi(new DateTimeImmutable($s), $m)->format('Y-m-d');
        $this->assertSame('2027-02-28', $d('2027-01-31', 1));
        $this->assertSame('2028-02-29', $d('2028-01-31', 1));
        $this->assertSame('2026-12-31', $d('2027-01-31', -1));
        $this->assertSame('2025-12-15', $d('2027-01-15', -13));
        $this->assertSame('2028-01-02', $d('2027-01-02', 12));
    }

    public function testRate(): void
    {
        $this->assertSame(['2027-01-02'], Scadenze::rate('2027-01-02', 'annuale'));
        $this->assertSame(['2026-07-02', '2027-01-02'], Scadenze::rate('2027-01-02', 'semestrale'));
        $this->assertCount(12, Scadenze::rate('2027-01-31', 'mensile'));
        $this->assertSame('2026-02-28', Scadenze::rate('2027-01-31', 'mensile')[0]);
    }

    public function testProssima(): void
    {
        $this->assertSame(['data' => '2026-07-02', 'evento' => 'rata'], Scadenze::prossima('2027-01-02', 'semestrale', '2026-03-01'));
        $this->assertSame(['data' => '2027-01-02', 'evento' => 'annualita'], Scadenze::prossima('2027-01-02', 'semestrale', '2026-07-03'));
        $this->assertNull(Scadenze::prossima('2026-01-02', 'annuale', '2026-01-03'));
    }

    public function testEsempioDelCliente(): void
    {
        // polizza annuale che scade il 2 gennaio 2027, avviso 15 giorni prima alle 9
        $p = ['scadenza' => '2027-01-02', 'frazionamento' => 'annuale', 'avvisa_cliente' => 1];
        $cfg = ['giorni_agenzia' => 15, 'giorni_cliente' => 15, 'ora' => 9, 'rate' => true, 'cliente' => false];
        $tz = new DateTimeZone('Europe/Rome');
        $this->assertSame([], Scadenze::daInviare($p, $cfg, new DateTimeImmutable('2026-12-17 23:00', $tz)));
        $this->assertSame([], Scadenze::daInviare($p, $cfg, new DateTimeImmutable('2026-12-18 08:59', $tz)));
        $this->assertSame([['evento' => 'annualita', 'data' => '2027-01-02', 'destinatario' => 'agenzia']], Scadenze::daInviare($p, $cfg, new DateTimeImmutable('2026-12-18 09:00', $tz)));
        $cfg['cliente'] = true;
        $this->assertCount(2, Scadenze::daInviare($p, $cfg, new DateTimeImmutable('2026-12-18 10:00', $tz)));
        $p['avvisa_cliente'] = 0;
        $this->assertCount(1, Scadenze::daInviare($p, $cfg, new DateTimeImmutable('2026-12-18 10:00', $tz)));
    }

    public function testRateSemestraliERiservaSoloAnnualita(): void
    {
        $p = ['scadenza' => '2027-01-02', 'frazionamento' => 'semestrale', 'avvisa_cliente' => 1];
        $cfg = ['giorni_agenzia' => 10, 'giorni_cliente' => 5, 'ora' => 0, 'rate' => true, 'cliente' => true];
        $r = Scadenze::daInviare($p, $cfg, new DateTimeImmutable('2026-06-25 12:00'));
        $this->assertSame([['evento' => 'rata', 'data' => '2026-07-02', 'destinatario' => 'agenzia']], $r);
        $cfg['rate'] = false;
        $this->assertSame([], Scadenze::daInviare($p, $cfg, new DateTimeImmutable('2026-06-25 12:00')));
    }

    public function testSegnaposto(): void
    {
        $this->assertSame('Mario: 02/01/2027 {ignoto}', Scadenze::compila('{cliente}: {scadenza} {ignoto}', ['cliente' => 'Mario', 'scadenza' => '02/01/2027']));
    }
}
