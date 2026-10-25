<?php

namespace Tests\Unit;

use App\Analyse\Gjenfinner;
use PHPUnit\Framework\TestCase;

class GjenfinnerTest extends TestCase
{
    public function test_forkortelser_matches_med_noyaktig_skrivemate(): void
    {
        $g = new Gjenfinner;
        $eu = $g->sokemonstre(['EU']);

        $this->assertTrue($g->harNokkelord('Norge skal ikke inn i EU.', $eu));
        $this->assertTrue($g->harNokkelord('EUs indre marked', $eu));
        $this->assertTrue($g->harNokkelord('Vi er mot EU-medlemskap', $eu));
        $this->assertFalse($g->harNokkelord('Europa trenger fred', $eu));
        $this->assertFalse($g->harNokkelord('Vi vil ha et eu-lignende forbund', $eu));
    }

    public function test_vanlige_ord_matcher_ordbegynnelse(): void
    {
        $g = new Gjenfinner;
        $m = $g->sokemonstre(['atomkraft']);

        $this->assertTrue($g->harNokkelord('Nye atomkraftverk bør utredes', $m));
        $this->assertTrue($g->harNokkelord('Atomkraft er ikke aktuelt', $m));
        $this->assertFalse($g->harNokkelord('Motstand mot fornybar kraft', $m));
    }

    public function test_forvekslinger_matcher_sammensatte_ord(): void
    {
        $g = new Gjenfinner;
        $m = $g->sokemonstre(['atomvåpen', 'eierbeskatning']);

        $this->assertTrue($g->harNokkelord('Verdens atomvåpenarsenaler utgjør en trussel', $m));
        $this->assertTrue($g->harNokkelord('Eierbeskatningen i Norge er for høy', $m));
        $this->assertFalse($g->harNokkelord('Vi vil bygge atomkraftverk', $m));
    }
}
