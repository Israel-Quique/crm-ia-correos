<?php

namespace Tests\Feature;

use App\Livewire\ProjectTestPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectTestPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_explains_that_a_401_means_siop_rejected_its_configured_token(): void
    {
        config([
            'services.postal.siop_token' => 'configured-test-token',
            'services.postal.siop_url' => 'https://siop.test/api/paquetes-eventos',
            'services.postal.sitra_url' => 'https://sitra.test/api/tracking/paquetes',
        ]);
        Http::fake([
            'siop.test/*' => Http::response([], 401),
            'sitra.test/*' => Http::response([], 200),
        ]);

        Livewire::test(ProjectTestPanel::class)
            ->call('runChecks')
            ->assertSee('SIOP rechazó autenticación')
            ->assertSee('HTTP 401')
            ->assertSee('El servicio rechazó el token configurado.')
            ->assertSee('PAQUETES_EVENTOS_API_TOKEN');
    }
}
