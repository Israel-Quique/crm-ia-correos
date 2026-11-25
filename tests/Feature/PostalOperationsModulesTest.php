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
        $national->update(['current_event' => 'Sin evento']);
        $otherScope = $this->createPackage('sitra', 'internacional', 'RR123456789XX');
        $event = PostalEvent::create([
            'postal_package_id' => $national->id,
            'source_event' => '4409',
            'event_name' => 'Paquete en camino para entrega física. Asignado a CARTERO por Ana de prueba a Bruno de prueba.',
            'event_at' => now()->subHour(),
        ]);
        PostalEvent::create([
            'postal_package_id' => $national->id,
            'source_event' => '',
            'event_name' => '',
            'event_at' => null,
        ]);

        Livewire::test(PostalTrackingManager::class, ['scope' => 'nacional'])
            ->assertSee('NA123456789')
            ->assertDontSee('RR123456789XX')
            ->call('openPackage', $national->id)
            ->assertSee('Estado del envío')
            ->assertSee('Cartero')
            ->assertSee('El paquete fue asignado a un cartero para intentar la entrega.')
            ->assertSee('Sin movimientos o sin clasificación confiable')
            ->assertDontSee('Ana de prueba')
            ->assertDontSee('4477')
            ->assertDontSee('Código')
            ->call('closePackage')
            ->assertSet('selectedPackageId', null)
            ->set('trackingFilter', 'NA123456789')
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
            ->set('eventFilter', 'Disponible en ventanilla')
            ->assertSee('NA000000016')
            ->set('trackingFilter', '000016')
            ->assertSee('NA000000016')
            ->set('recipientFilter', 'no existe')
            ->assertSee('No se encontraron paquetes')
            ->call('clearTableFilters')
            ->assertSee('Mostrando 1–14 de 16');
    }

    public function test_tracking_list_excludes_packages_without_update_date(): void
    {
        $this->actingAs(User::factory()->create());

        $dated = $this->createPackage('siop', 'nacional', 'NA123456789');
        $dated->update(['updated_at_api' => now()->subDay()]);
        $withoutDate = $this->createPackage('siop', 'nacional', 'NA987654321');
        $withoutDate->update(['updated_at_api' => null]);

        Livewire::test(PostalTrackingManager::class, ['scope' => 'nacional'])
            ->assertSee('NA123456789')
            ->assertDontSee('NA987654321')
            ->assertSee('1 resultados');
    }

    public function test_tracking_scope_tabs_keep_national_and_international_packages_separate(): void
    {
        $this->actingAs(User::factory()->create());
        $national = $this->createPackage('siop', 'nacional', 'NA123456780');
        $international = $this->createPackage('sitra', 'internacional', 'RR123456780XX');

        Livewire::test(PostalTrackingManager::class, ['scope' => 'nacional'])
            ->assertSee('NA123456780')
            ->assertDontSee('RR123456780XX')
            ->assertSee('Clasificación del evento (SIOP)')
            ->assertSee('códigos de SIOP (nacional)')
            ->set('scope', 'internacional')
            ->assertSee('RR123456780XX')
            ->assertDontSee('NA123456780')
            ->assertSee('Clasificación del evento (SITRA)')
            ->assertSee('códigos de SITRA (internacional)')
            ->assertSet('selectedPackageId', null);

        $this->assertDatabaseHas('postal_packages', ['id' => $national->id, 'scope' => 'nacional']);
        $this->assertDatabaseHas('postal_packages', ['id' => $international->id, 'scope' => 'internacional']);
    }

    public function test_tracking_event_filter_includes_no_event_and_only_current_scope_events(): void
    {
        $this->actingAs(User::factory()->create());
        $national = $this->createPackage('siop', 'nacional', 'NA123456781');
        $national->update(['current_event' => 'En Camino']);
        PostalEvent::create([
            'postal_package_id' => $national->id,
            'source_event' => '168',
            'event_name' => 'El paquete llegó a una oficina de tránsito internacional',
            'event_at' => now(),
        ]);
        $nationalWithoutEvent = $this->createPackage('siop', 'nacional', 'NA123456782');
        $nationalWithoutEvent->update(['current_event' => null]);
        $nationalWithoutEventStatus = $this->createPackage('siop', 'nacional', 'NA123456783');
        $nationalWithoutEventStatus->update(['current_event' => 'Sin evento']);
        $nationalWithUnclassifiedCode = $this->createPackage('siop', 'nacional', 'NA123456784');
        PostalEvent::create([
            'postal_package_id' => $nationalWithUnclassifiedCode->id,
            'source_event' => '9999',
            'event_name' => 'Evento interno sin catálogo',
            'event_at' => now(),
        ]);
        $national297Transit = $this->createPackage('siop', 'nacional', 'NA123456785');
        PostalEvent::create([
            'postal_package_id' => $national297Transit->id,
            'source_event' => '297',
            'event_name' => 'Paquete recibido en oficina origen de tránsito',
            'event_at' => now(),
        ]);
        $national297Destination = $this->createPackage('siop', 'nacional', 'NA123456786');
        PostalEvent::create([
            'postal_package_id' => $national297Destination->id,
            'source_event' => '297',
            'event_name' => 'Paquete recibido en oficina de destino',
            'event_at' => now(),
        ]);
        $national297Origin = $this->createPackage('siop', 'nacional', 'NA123456787');
        PostalEvent::create([
            'postal_package_id' => $national297Origin->id,
            'source_event' => '297',
            'event_name' => 'Paquete recibido del cliente',
            'event_at' => now(),
        ]);
        $nationalPickup = $this->createPackage('siop', 'nacional', 'NA123456788');
        $nationalPickup->update(['current_event' => 'Disponible para recojo en ventanilla']);
        $nationalPickupCode = $this->createPackage('siop', 'nacional', 'NA123456789');
        PostalEvent::create([
            'postal_package_id' => $nationalPickupCode->id,
            'source_event' => '312',
            'event_name' => 'Paquete recibido en oficina de destino',
            'event_at' => now(),
        ]);
        $nationalPastPickup = $this->createPackage('siop', 'nacional', 'NA123456790');
        PostalEvent::create([
            'postal_package_id' => $nationalPastPickup->id,
            'source_event' => '168',
            'event_name' => 'El paquete sigue en tránsito',
            'event_at' => now()->subHours(2),
        ]);
        PostalEvent::create([
            'postal_package_id' => $nationalPastPickup->id,
            'source_event' => '312',
            'event_name' => 'Disponible para recojo en ventanilla',
            'event_at' => now()->subHour(),
        ]);
        PostalEvent::create([
            'postal_package_id' => $nationalPastPickup->id,
            'source_event' => '316',
            'event_name' => 'Paquete entregado al destinatario',
            'event_at' => now(),
        ]);

        $international = $this->createPackage('sitra', 'internacional', 'RR123456781XX');
        $international->update(['current_event' => 'Entregado']);
        PostalEvent::create([
            'postal_package_id' => $international->id,
            'source_event' => '37',
            'event_name' => 'Entregado en el país de destino',
            'event_at' => now(),
        ]);
        $internationalPickup = $this->createPackage('sitra', 'internacional', 'RR123456782XX');
        PostalEvent::create([
            'postal_package_id' => $internationalPickup->id,
            'source_event' => '32',
            'event_name' => 'En oficina postal de entrega',
            'event_at' => now(),
        ]);

        Livewire::test(PostalTrackingManager::class, ['scope' => 'nacional'])
            ->assertSee('<option value="category:En%20Camino">En Camino</option>', false)
            ->assertSee('<option value="category:Ventanilla">Ventanilla</option>', false)
            ->assertDontSee('<option value="category:Cancelado">Cancelado</option>', false)
            ->assertDontSee('<option value="category:Sin%20movimientos%20o%20sin%20clasificaci%C3%B3n%20confiable">Sin movimientos o sin clasificación confiable</option>', false)
            ->assertDontSee('Aduana')
            ->assertSee('Recibido Origen')
            ->set('eventFilter', 'category:'.rawurlencode('En Camino'))
            ->assertSee('NA123456781')
            ->assertSee('NA123456785')
            ->assertDontSee('NA123456786')
            ->assertDontSee('NA123456782')
            ->assertDontSee('NA123456790')
            ->set('eventFilter', 'category:'.rawurlencode('Recibido Destino'))
            ->assertSee('NA123456786')
            ->assertDontSee('NA123456785')
            ->set('eventFilter', 'category:'.rawurlencode('Recibido Origen'))
            ->assertSee('NA123456787')
            ->assertDontSee('NA123456786')
            ->set('eventFilter', 'category:Ventanilla')
            ->assertSee('NA123456788')
            ->assertSee('NA123456789')
            ->assertDontSee('NA123456786')
            ->assertDontSee('NA123456790')
            ->set('eventFilter', 'category:Entregado')
            ->assertSee('NA123456790')
            ->assertDontSee('NA123456785')
            ->set('eventFilter', '')
            ->assertSee('El sistema marca el paquete como entregado.')
            ->call('openPackage', $nationalPastPickup->id)
            ->assertSee('Tu paquete ya llegó a la oficina de entrega o destino y está listo para entregar o recoger')
            ->assertSee('El sistema marca el paquete como entregado.')
            ->set('eventFilter', 'category:'.rawurlencode('Sin movimientos o sin clasificación confiable'))
            ->assertSee('Mostrando 1–10 de 10')
            ->call('openPackage', $nationalWithoutEvent->id)
            ->assertSee('Sin evento')
            ->set('scope', 'internacional')
            ->assertSet('eventFilter', '')
            ->assertSee('RR123456781XX')
            ->assertSee('Aduana')
            ->assertSee('Origen y llegada')
            ->assertSee('Distribución nacional')
            ->assertSee('Última milla')
            ->assertSee('<option value="category:Ventanilla">Ventanilla</option>', false)
            ->assertDontSee('<option value="category:Importaci%C3%B3n%20detenida">Importación detenida</option>', false)
            ->assertDontSee('<option value="category:Sin%20movimientos%20o%20sin%20clasificaci%C3%B3n%20confiable">Sin movimientos o sin clasificación confiable</option>', false)
            ->set('eventFilter', 'category:'.rawurlencode('Importación detenida'))
            ->assertSee('RR123456781XX')
            ->assertSee('Mostrando 1–2 de 2')
            ->set('eventFilter', 'category:'.rawurlencode('Sin movimientos o sin clasificación confiable'))
            ->assertSee('RR123456781XX')
            ->assertSee('Mostrando 1–2 de 2')
            ->set('eventFilter', '__sin_evento__')
            ->assertSee('RR123456781XX')
            ->assertSee('Mostrando 1–2 de 2')
            ->set('eventFilter', 'category:Ventanilla')
            ->assertSee('RR123456782XX')
            ->assertDontSee('RR123456781XX')
            ->assertDontSee('Recibido Origen')
            ->assertDontSee('NA123456781');
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
