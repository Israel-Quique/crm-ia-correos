<?php

namespace Tests\Feature;

use App\Livewire\PostalTrackingManager;
use App\Livewire\TariffManager;
use App\Models\PostalEvent;
use App\Models\PostalPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PostalOperationsModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_module_routes_are_available_from_the_authenticated_navigation(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('tariffs'))->assertOk()->assertSee('Módulo de tarifas')->assertSee('Tarifas');
        $this->get(route('tracking.national'))
            ->assertOk()
            ->assertSee('Rastreo postal')
            ->assertSee('Nacional')
            ->assertSee('Internacional');
        $this->get(route('tracking.international'))
            ->assertOk()
            ->assertSee('Rastreo postal')
            ->assertSee('Nacional')
            ->assertSee('Internacional');
    }

    public function test_tracking_lists_only_the_selected_scope_and_shows_package_event_details(): void
    {
        $this->actingAs(User::factory()->create());
        $national = $this->createPackage('siop', 'nacional', 'NA123456789');
        $otherScope = $this->createPackage('sitra', 'internacional', 'RR123456789XX');
        $event = PostalEvent::create([
            'postal_package_id' => $national->id,
            'source_event' => '315',
            'event_name' => 'En distribución',
            'event_at' => now()->subHour(),
        ]);

        Livewire::test(PostalTrackingManager::class, ['scope' => 'nacional'])
            ->assertSee('NA123456789')
            ->assertDontSee('RR123456789XX')
            ->call('openPackage', $national->id)
            ->assertSee('En distribución')
            ->assertSee('315')
            ->call('closePackage')
            ->assertDontSee('tracking-detail-'.$national->id);

        $this->assertDatabaseHas('postal_packages', ['id' => $otherScope->id, 'scope' => 'internacional']);
        $this->assertDatabaseHas('postal_events', ['id' => $event->id]);
    }

    public function test_tracking_table_filters_and_paginates_fourteen_packages(): void
    {
        $this->actingAs(User::factory()->create());

        for ($index = 1; $index <= 16; $index++) {
            $package = $this->createPackage('siop', 'nacional', 'NA'.str_pad((string) $index, 9, '0', STR_PAD_LEFT));
            $package->update([
                'sender' => $index === 16 ? 'Correo de prueba' : 'Remitente '.$index,
                'recipient' => 'Destinatario '.$index,
                'current_event' => $index === 16 ? 'Disponible en ventanilla' : 'En camino',
                'updated_at_api' => now()->subDays($index),
            ]);
        }

        Livewire::test(PostalTrackingManager::class, ['scope' => 'nacional'])
            ->assertSee('NA000000001')
            ->assertSee('NA000000014')
            ->assertDontSee('NA000000015')
            ->assertSee('Mostrando 1–14 de 16')
            ->call('gotoPage', 2)
            ->assertSee('NA000000015')
            ->assertSee('NA000000016')
            ->assertDontSee('NA000000014')
            ->set('senderFilter', 'Correo de prueba')
            ->assertSee('NA000000016')
            ->assertDontSee('NA000000002')
            ->assertSee('Mostrando 1–1 de 1')
            ->set('eventFilter', 'ventanilla')
            ->assertSee('NA000000016')
            ->set('trackingFilter', '000016')
            ->assertSee('NA000000016')
            ->set('recipientFilter', 'no existe')
            ->assertSee('No se encontraron paquetes')
            ->call('clearTableFilters')
            ->assertSee('Mostrando 1–14 de 16');
    }

    public function test_tracking_scope_tabs_keep_national_and_international_packages_separate(): void
    {
        $this->actingAs(User::factory()->create());
        $national = $this->createPackage('siop', 'nacional', 'NA123456780');
        $international = $this->createPackage('sitra', 'internacional', 'RR123456780XX');

        Livewire::test(PostalTrackingManager::class, ['scope' => 'nacional'])
            ->assertSee('NA123456780')
            ->assertDontSee('RR123456780XX')
            ->set('scope', 'internacional')
            ->assertSee('RR123456780XX')
            ->assertDontSee('NA123456780')
            ->assertSet('selectedPackageId', null);

        $this->assertDatabaseHas('postal_packages', ['id' => $national->id, 'scope' => 'nacional']);
        $this->assertDatabaseHas('postal_packages', ['id' => $international->id, 'scope' => 'internacional']);
    }

    public function test_tracking_module_can_sync_the_selected_national_api(): void
    {
        $this->actingAs(User::factory()->create());
        config([
            'services.postal.siop_url' => 'https://siop.test/api/packages',
            'services.postal.siop_token' => 'test-token',
        ]);
        Http::fake([
            'siop.test/*' => Http::response([
                'data' => [[
                    'id' => 'sync-package-1',
                    'tracking_number' => 'NA987654321',
                    'updated_at' => now()->toIso8601String(),
                    'status' => 'En camino',
                ]],
            ]),
        ]);

        Livewire::test(PostalTrackingManager::class, ['scope' => 'nacional'])
            ->call('openSyncModal')
            ->assertSee('role="dialog"', false)
            ->assertSee('Iniciar sincronización')
            ->set('from', now()->toDateString())
            ->set('to', now()->toDateString())
            ->call('syncNow')
            ->assertHasNoErrors()
            ->assertSee('Sincronizar nacional')
            ->assertSee('SIOP: sincronización completada')
            ->assertSee('Paquetes recibidos: 1');

        $this->assertDatabaseHas('postal_packages', [
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'sync-package-1',
            'tracking_number' => 'NA987654321',
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'siop.test'));
    }

    public function test_tracking_module_can_sync_the_selected_international_api(): void
    {
        $this->actingAs(User::factory()->create());
        config([
            'services.postal.sitra_url' => 'https://sitra.test/api/packages',
            'services.postal.sitra_token' => 'test-token',
        ]);
        Http::fake([
            'sitra.test/*' => Http::response([
                'data' => [[
                    'id' => 'sync-international-package-1',
                    'tracking_number' => 'RR123456789XX',
                    'updated_at' => now()->toIso8601String(),
                    'status' => 'En tránsito',
                ]],
            ]),
        ]);

        Livewire::test(PostalTrackingManager::class, ['scope' => 'internacional'])
            ->call('openSyncModal')
            ->assertSee('role="dialog"', false)
            ->set('from', now()->toDateString())
            ->set('to', now()->toDateString())
            ->call('syncNow')
            ->assertHasNoErrors()
            ->assertSee('Sincronizar internacional')
            ->assertSee('SITRA: sincronización completada')
            ->assertSee('Paquetes recibidos: 1');

        $this->assertDatabaseHas('postal_packages', [
            'source' => 'sitra',
            'scope' => 'internacional',
            'external_id' => 'sync-international-package-1',
            'tracking_number' => 'RR123456789XX',
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sitra.test'));
    }

    public function test_tariff_module_loads_delivery_express_rates_from_the_configured_api(): void
    {
        $this->actingAs(User::factory()->create());
        config([
            'services.delivery_express.url' => 'https://delivery.test/api/rates',
            'services.delivery_express.token' => 'test-token',
        ]);
        Http::fake([
            'delivery.test/*' => Http::response([[
                'origen' => ['nombre_origen' => 'LA PAZ'],
                'destino' => ['nombre_destino' => 'SANTA CRUZ'],
                'servicio_extra' => ['nombre' => 'PUERTA A PUERTA'],
                'peso1' => '25.00',
            ]]),
        ]);

        Livewire::test(TariffManager::class)
            ->call('loadDeliveryRates')
            ->assertHasNoErrors()
            ->assertSee('Tarifas de Delivery Express')
            ->assertSee('LA PAZ')
            ->assertSee('SANTA CRUZ')
            ->assertSee('Bs. 25.00')
            ->assertDontSee('Rango de peso');
    }

    public function test_delivery_express_rates_are_paginated_and_search_resets_to_first_page(): void
    {
        $this->actingAs(User::factory()->create());
        config([
            'services.delivery_express.url' => 'https://delivery.test/api/rates',
            'services.delivery_express.token' => 'test-token',
        ]);
        Http::fake([
            'delivery.test/*' => Http::response(array_map(
                fn (int $index): array => [
                    'origen' => ['nombre_origen' => 'ORIGEN '.str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
                    'destino' => ['nombre_destino' => 'DESTINO '.str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
                    'servicio_extra' => ['nombre' => 'PUERTA A PUERTA'],
                    'peso1' => number_format($index, 2, '.', ''),
                ],
                range(1, 25),
            )),
        ]);

        Livewire::test(TariffManager::class)
            ->call('loadDeliveryRates')
            ->assertSee('ORIGEN 01')
            ->assertSee('ORIGEN 20')
            ->assertDontSee('ORIGEN 21')
            ->assertSee('Mostrando 1–20 de 25 tarifas')
            ->call('gotoPage', 2)
            ->assertSee('ORIGEN 21')
            ->assertSee('ORIGEN 25')
            ->assertDontSee('ORIGEN 20')
            ->assertSee('Mostrando 21–25 de 25 tarifas')
            ->set('rateSearch', 'ORIGEN 25')
            ->assertSee('ORIGEN 25')
            ->assertDontSee('ORIGEN 21')
            ->assertSee('Mostrando 1–1 de 1 tarifas');
    }

    public function test_delivery_express_can_filter_rates_by_department_and_modality(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TariffManager::class)
            ->set('deliveryRates', [
                [
                    'origen' => ['nombre_origen' => 'LA PAZ'],
                    'destino' => ['nombre_destino' => 'SANTA CRUZ'],
                    'servicio_extra' => ['nombre' => 'SERVICIO PUERTA A PUERTA'],
                    'peso1' => '25.00',
                ],
                [
                    'origen' => ['nombre_origen' => 'LA PAZ'],
                    'destino' => ['nombre_destino' => 'SANTA CRUZ'],
                    'servicio_extra' => ['nombre' => 'SERVICIO DE PUERTA A VENTANILLA'],
                    'peso1' => '20.00',
                ],
                [
                    'origen' => ['nombre_origen' => 'SANTA CRUZ'],
                    'destino' => ['nombre_destino' => 'LA PAZ'],
                    'servicio_extra' => ['nombre' => 'SERVICIO PUERTA A PUERTA'],
                    'peso1' => '30.00',
                ],
            ])
            ->assertSee('Todos los departamentos')
            ->assertSee('Todas las modalidades')
            ->set('rateDepartment', 'SANTA CRUZ')
            ->assertSee('Bs. 25.00')
            ->assertSee('Bs. 20.00')
            ->assertDontSee('Bs. 30.00')
            ->set('rateMode', 'SERVICIO PUERTA A PUERTA')
            ->assertSee('Bs. 25.00')
            ->assertDontSee('Bs. 20.00')
            ->assertDontSee('Bs. 30.00');
    }

    public function test_tariff_module_queries_ems_rates_using_the_postar_api(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake([
            'postar.correos.gob.bo/*' => Http::response([
                'success' => true,
                'tarifa' => 37.5,
                'categoria' => 'EMS NAT',
                'destino' => 'nacional_santa_cruz',
            ]),
        ]);

        Livewire::test(TariffManager::class)
            ->set('category', 'EMS NAT')
            ->set('destination', 'nacional_santa_cruz')
            ->set('weight', '2')
            ->call('quoteEms')
            ->assertHasNoErrors()
            ->assertSee('role="dialog"', false)
            ->assertSee('class="ems-quote-result"', false)
            ->assertSee('aria-label="Cerrar"', false)
            ->assertSee('Bs. 37.50');
    }

    public function test_ems_destination_options_follow_the_selected_category(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TariffManager::class)
            ->set('activeTab', 'ems')
            ->assertSee('<option value="nacional_santa_cruz">Santa Cruz</option>', false)
            ->assertDontSee('<option value="Argentina">Argentina</option>', false)
            ->set('category', 'EMS INT')
            ->assertSee('<option value="Argentina">Argentina</option>', false)
            ->assertDontSee('<option value="nacional_santa_cruz">Santa Cruz</option>', false)
            ->assertSet('destination', '');
    }

    private function createPackage(string $source, string $scope, string $trackingNumber): PostalPackage
    {
        return PostalPackage::create([
            'source' => $source,
            'scope' => $scope,
            'external_id' => 'operations-'.$trackingNumber,
            'tracking_number' => $trackingNumber,
            'sender' => 'Remitente',
            'recipient' => 'Destinatario',
            'current_event' => 'En proceso',
            'updated_at_api' => now(),
        ]);
    }
}
