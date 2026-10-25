<?php

namespace Tests\Unit;

use App\Digitalisering\Setningsdeler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SetningsdelerTest extends TestCase
{
    #[DataProvider('eksempler')]
    public function test_deler_setninger(string $tekst, array $forventet): void
    {
        $this->assertSame($forventet, array_column((new Setningsdeler)->del($tekst), 'tekst'));
    }

    public static function eksempler(): array
    {
        return [
            'enkle setninger' => ['Vi vil styrke forsvaret. Norge skal være med i NATO!', ['Vi vil styrke forsvaret.', 'Norge skal være med i NATO!']],
            'forkortelser deler ikke' => ['Vi vil bl.a. styrke Heimevernet, jf. NATOs mål. Det koster ca. 20 mrd. kr. Neste setning.', ['Vi vil bl.a. styrke Heimevernet, jf. NATOs mål.', 'Det koster ca. 20 mrd. kr. Neste setning.']],
            'f.eks. foran stor bokstav' => ['Vi vil samarbeide med f.eks. Sverige. Det er viktig.', ['Vi vil samarbeide med f.eks. Sverige.', 'Det er viktig.']],
            'osv. kan avslutte' => ['Vi vil bygge vei, bane osv. Dette gir vekst.', ['Vi vil bygge vei, bane osv.', 'Dette gir vekst.']],
            'dato med ordenstall' => ['Grunnloven ble vedtatt 17. mai 1814. Den gjelder fortsatt.', ['Grunnloven ble vedtatt 17. mai 1814.', 'Den gjelder fortsatt.']],
            'initialer' => ['Talen ble holdt av A. Hansen i fjor. Den var lang.', ['Talen ble holdt av A. Hansen i fjor.', 'Den var lang.']],
            'sitat' => ['Partiet sier «nei til EU». Det står fast.', ['Partiet sier «nei til EU».', 'Det står fast.']],
            'punktliste' => ["Partiet vil:\n• Øke antall soldater.\n• Kjøpe ubåter. Bygge dem i Norge.", ['Partiet vil:', 'Øke antall soldater.', 'Kjøpe ubåter.', 'Bygge dem i Norge.']],
        ];
    }

    public function test_merker_listepunkter(): void
    {
        $setninger = (new Setningsdeler)->del("Partiet vil:\n• Øke antall soldater.");

        $this->assertFalse($setninger[0]['er_punkt']);
        $this->assertTrue($setninger[1]['er_punkt']);
    }
}
