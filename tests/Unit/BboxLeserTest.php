<?php

namespace Tests\Unit;

use App\Digitalisering\BboxLeser;
use PHPUnit\Framework\TestCase;

class BboxLeserTest extends TestCase
{
    public function test_kontrolltegn_i_teksten_hindrer_ikke_lesing(): void
    {
        // MDGs arbeidsprogram har backspace-tegn (0x08) i teksten.
        $xhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><doc><page width="595" height="842"><flow><block><line xMin="50" yMin="100" xMax="300" yMax="112">'
            ."<word xMin=\"50\" yMin=\"100\" xMax=\"90\" yMax=\"112\">Grønn\x08</word><word xMin=\"95\" yMin=\"100\" xMax=\"140\" yMax=\"112\">politikk</word>"
            .'</line></block></flow></page></doc></body></html>';

        $resultat = (new BboxLeser)->tolk($xhtml);

        $this->assertSame(1, $resultat['sider']);
        $this->assertNotEmpty($resultat['linjer']);
    }
}
