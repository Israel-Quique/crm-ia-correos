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

    public function test_it_checks_ollama_and_reports_the_configured_model(): void
    {
        config([
            'services.ollama.url' => 'http://ollama.test',
            'services.ollama.model' => 'llama3.2:3b',
            'services.postal.siop_url' => 'https://siop.test',
            'services.postal.sitra_url' => 'https://sitra.test',
        ]);
        Http::fake([
            'ollama.test/api/tags' => Http::response(['models' => [['name' => 'llama3.2:3b']]], 200),
            'siop.test/*' => Http::response([], 200),
            'sitra.test/*' => Http::response([], 200),
        ]);

        Livewire::test(ProjectTestPanel::class)
            ->call('runChecks')
            ->assertSee('Ollama conectado')
            ->assertSee('Modelo llama3.2:3b disponible');
    }
}
