<?php

namespace Tests\Feature;

use Tests\TestCase;

class MatomoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.matomo.host' => 'analytics.laaknor.no', 'services.matomo.site_id' => '8']);
    }

    public function test_forsiden_har_sporingskoden_med_host_og_site_id_fra_konfigurasjonen(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee("_paq.push(['trackPageView']);", false)
            ->assertSee("'\/\/analytics.laaknor.no\/'", false)
            ->assertSee("_paq.push(['setSiteId', '8']);", false);
    }

    public function test_host_kan_oppgis_med_protokoll_og_skraastrek(): void
    {
        config(['services.matomo.host' => 'https://analytics.example.org/']);

        $this->get('/')->assertSee("'\/\/analytics.example.org\/'", false);
    }

    public function test_ingen_sporingskode_uten_host_eller_site_id(): void
    {
        config(['services.matomo.host' => '']);
        $this->get('/')->assertDontSee('matomo.php', false);

        config(['services.matomo.host' => 'analytics.laaknor.no', 'services.matomo.site_id' => null]);
        $this->get('/')->assertDontSee('matomo.php', false);
    }

    public function test_sokeside_sporer_internt_sok_i_stedet_for_sidevisning(): void
    {
        $html = (string) $this->blade('<x-matomo sok="NATO" sokekategori="Tema" :treff="9" />');

        $this->assertStringContainsString("_paq.push(['trackSiteSearch', 'NATO', 'Tema', 9]);", $html);
        $this->assertStringNotContainsString("_paq.push(['trackPageView']);", $html);
    }

    public function test_tomt_sok_gir_vanlig_sidevisning(): void
    {
        $html = (string) $this->blade('<x-matomo sok="" />');

        $this->assertStringContainsString("_paq.push(['trackPageView']);", $html);
        $this->assertStringNotContainsString("_paq.push(['trackSiteSearch', '", $html);
    }

    public function test_sokeord_escapes_i_skriptet(): void
    {
        $html = (string) $this->blade('<x-matomo :sok="$sok" />', ['sok' => "</script><script>alert('x')"]);

        $this->assertStringNotContainsString('</script><script>alert', $html);
    }

    public function test_admin_har_ikke_sporingskode(): void
    {
        $this->get('/admin/login')->assertOk()->assertDontSee('matomo.php', false);
    }
}
