<?php

namespace Tests\Feature;

use App\Jobs\SyncCdsDeclarationsForCampaign;
use App\Jobs\SendPostalCampaignMessages;
use App\Livewire\PostalCampaignManager;
use App\Livewire\PostalCampaignRegistry;
use App\Livewire\WhatsAppTestNumbers;
use App\Livewire\Chat;
use App\Models\PostalCampaign;
use App\Models\PostalCampaignMessage;
use App\Models\PostalPackage;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class PostalCampaignCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_national_campaign_from_the_campaign_page(): void
    {
        $this->assertCampaignCanBeCreated('nacional', [
            'source' => 'siop',
            'phone' => '+59170000001',
        ]);
    }

    public function test_it_can_create_an_international_campaign_from_the_campaign_page(): void
    {
        $this->assertCampaignCanBeCreated('internacional', [
            'source' => 'sitra',
            'phone' => null,
            'cds_phone' => '+59170000002',
        ]);
    }

    public function test_national_campaign_page_shows_registry_and_moves_creation_into_a_modal(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('postal-campaigns.national'))
            ->assertRedirect(route('postal-campaigns.national.registry'));

        $this->get(route('postal-campaigns.national.registry'))
            ->assertOk()
            ->assertSee('Campañas nacionales')
            ->assertSee('Crear campaña')
            ->assertSee('registryCreateCampaign')
            ->assertDontSee('Filtrar paquetes')
            ->assertSee('href="'.route('postal-campaigns.national.registry').'"', false)
            ->assertDontSee('Registro de campañas');

        Livewire::test(\App\Livewire\PostalCampaignRegistry::class, ['scope' => 'nacional'])
            ->call('openCreateCampaign')
            ->assertSet('showCreateCampaign', true)
            ->assertSee('Nueva campaña nacional');

        Livewire::test(PostalCampaignManager::class, [
            'scope' => 'nacional',
            'modalMode' => true,
        ])
            ->assertDontSee('Aplicar filtros')
            ->assertDontSee('Limpiar filtros');
    }

    public function test_national_and_international_registry_pages_are_the_primary_campaign_links(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            [
                'legacy' => 'postal-campaigns.national',
                'registry' => 'postal-campaigns.national.registry',
                'heading' => 'Campañas nacionales',
            ],
            [
                'legacy' => 'postal-campaigns.international',
                'registry' => 'postal-campaigns.international.registry',
                'heading' => 'Campañas internacionales',
            ],
        ] as $campaignRoutes) {
            $this->get(route($campaignRoutes['legacy']))
                ->assertRedirect(route($campaignRoutes['registry']));

            $this->get(route($campaignRoutes['registry']))
                ->assertOk()
                ->assertSee($campaignRoutes['heading'])
                ->assertSee('href="'.route($campaignRoutes['registry']).'"', false);
        }
    }

    public function test_modal_campaign_creation_uses_current_filters_and_notifies_registry(): void
    {
        $this->actingAs(User::factory()->create());
        PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'modal-creator-package',
            'tracking_number' => 'NA123456782',
            'phone' => '+59170000003',
            'recipient' => 'Destinatario modal',
            'current_event' => 'En proceso',
            'updated_at_api' => now(),
        ]);

        $creator = Livewire::test(PostalCampaignManager::class, [
            'scope' => 'nacional',
            'modalMode' => true,
        ])
            ->assertSee('Filtrar paquetes SIOP')
            ->assertSee('Configuración de campaña')
            ->set('testMode', false)
            ->set('campaignName', 'Campaña desde modal')
            ->call('prepareCampaign')
            ->assertHasNoErrors()
            ->assertDispatched('campaign-created');

        $this->assertDatabaseHas('postal_campaigns', [
            'name' => 'Campaña desde modal',
            'total' => 1,
        ]);
    }

    public function test_campaign_registry_displays_the_selected_date_range(): void
    {
        $this->actingAs(User::factory()->create());

        $from = now()->subDays(2)->toDateString();
        $to = now()->subDay()->toDateString();
        PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'date-range-campaign-included',
            'tracking_number' => 'NA123456780',
            'phone' => '+59170000009',
            'recipient' => 'Dentro del rango',
            'current_event' => 'En proceso',
            'updated_at_api' => now()->subDay(),
        ]);
        PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'date-range-campaign-excluded',
            'tracking_number' => 'NA123456781',
            'phone' => '+59170000010',
            'recipient' => 'Fuera del rango',
            'current_event' => 'En proceso',
            'updated_at_api' => now()->subDays(5),
        ]);

        Livewire::test(PostalCampaignManager::class, [
            'scope' => 'nacional',
            'modalMode' => true,
        ])
            ->set('testMode', false)
            ->set('from', $from)
            ->set('to', $to)
            ->set('campaignName', 'Campaña con rango')
            ->call('prepareCampaign')
            ->assertHasNoErrors();

        $campaign = PostalCampaign::where('name', 'Campaña con rango')->firstOrFail();
        $this->assertSame($from, $campaign->filters['from']);
        $this->assertSame($to, $campaign->filters['to']);
        $this->assertSame(1, $campaign->total);

        Livewire::test(\App\Livewire\PostalCampaignRegistry::class, ['scope' => 'nacional'])
            ->assertSee('Rango de fechas')
            ->assertSee(now()->subDays(2)->format('d/m/Y').' - '.now()->subDay()->format('d/m/Y'));
    }

    public function test_modal_updates_editable_maximum_to_the_selected_event_count(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['NA123456783', 'NA123456784'] as $index => $trackingNumber) {
            PostalPackage::create([
                'source' => 'siop',
                'scope' => 'nacional',
                'external_id' => 'event-count-package-'.$index,
                'tracking_number' => $trackingNumber,
                'phone' => '+5917000000'.($index + 4),
                'recipient' => 'Destinatario '.$index,
                'current_event' => 'En camino',
                'updated_at_api' => now(),
            ]);
        }

        Livewire::test(PostalCampaignManager::class, [
            'scope' => 'nacional',
            'modalMode' => true,
        ])
            ->set('testMode', false)
            ->assertSee('Eventos nacionales')
            ->assertDontSee('Eventos internacionales')
            ->assertSee('campaign-event-button', false)
            ->assertDontSee('<select wire:model.live="eventFilter"', false)
            ->assertDontSee('Modo prueba')
            ->assertDontSee('Modo real activo')
            ->assertDontSee('wire:model="template"', false)
            ->call('filterByEvent', 'En Camino')
            ->assertSet('limit', 2)
            ->assertSee('Camino (2)')
            ->set('limit', 1)
            ->assertSet('limit', 1);
    }

    public function test_national_and_international_modals_use_the_full_event_count_above_one_thousand(): void
    {
        $this->actingAs(User::factory()->create());
        Queue::fake();

        $timestamp = now();
        $packages = [];
        foreach ([
            ['scope' => 'nacional', 'source' => 'siop', 'tracking_prefix' => 'NA', 'event' => 'En camino', 'bucket' => 'En Camino'],
            ['scope' => 'internacional', 'source' => 'sitra', 'tracking_prefix' => 'RR', 'event' => 'En aduana', 'bucket' => 'Aduana'],
        ] as $campaign) {
            for ($index = 0; $index < 1005; $index++) {
                $packages[] = [
                    'source' => $campaign['source'],
                    'scope' => $campaign['scope'],
                    'external_id' => 'large-event-count-'.$campaign['scope'].'-'.$index,
                    'tracking_number' => $campaign['tracking_prefix'].str_pad((string) $index, 9, '0', STR_PAD_LEFT),
                    'phone' => '+5917'.str_pad((string) $index, 7, '0', STR_PAD_LEFT),
                    'current_event' => $campaign['event'],
                    'updated_at_api' => $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }
        PostalPackage::insert($packages);

        foreach ([
            ['scope' => 'nacional', 'event' => 'En Camino', 'buttonLabel' => 'Camino'],
            ['scope' => 'internacional', 'event' => 'Aduana', 'buttonLabel' => 'Aduana'],
        ] as $campaign) {
            Livewire::test(PostalCampaignManager::class, [
                'scope' => $campaign['scope'],
                'modalMode' => true,
            ])
                ->set('testMode', false)
                ->set('eventFilter', $campaign['event'])
                ->assertSet('limit', 1005)
                ->assertSee($campaign['buttonLabel'].' (1005)')
                ->assertSee('max="1005"', false);
        }
    }

    public function test_modal_event_counts_include_all_packages_even_when_test_mode_is_enabled(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['NA123456789', 'NA123456790'] as $index => $trackingNumber) {
            PostalPackage::create([
                'source' => 'siop',
                'scope' => 'nacional',
                'external_id' => 'all-event-count-package-'.$index,
                'tracking_number' => $trackingNumber,
                'phone' => '+5917999000'.$index,
                'recipient' => 'Destinatario '.$index,
                'current_event' => 'En camino',
                'updated_at_api' => now(),
            ]);
        }

        Livewire::test(PostalCampaignManager::class, [
            'scope' => 'nacional',
            'modalMode' => true,
        ])
            ->set('testMode', true)
            ->assertSet('testMode', true)
            ->assertSee('Todos (2)')
            ->assertSee('Camino (2)')
            ->set('eventFilter', 'En Camino')
            ->assertSet('limit', 2)
            ->assertSee('2 paquetes en En Camino para el rango seleccionado');
    }

    public function test_modal_event_dropdown_only_counts_packages_with_phone_inside_selected_dates(): void
    {
        $this->actingAs(User::factory()->create());

        $packages = [
            ['tracking' => 'NA123456791', 'phone' => '+59179991001', 'event' => 'En camino', 'date' => now()->subDays(2)],
            ['tracking' => 'NA123456792', 'phone' => '+59179991002', 'event' => 'Recibido Origen', 'date' => now()->subDay()],
            ['tracking' => 'NA123456793', 'phone' => null, 'event' => 'En camino', 'date' => now()->subDay()],
            ['tracking' => 'NA123456794', 'phone' => '+59179991004', 'event' => 'En camino', 'date' => now()->subDays(6)],
        ];

        foreach ($packages as $index => $package) {
            PostalPackage::create([
                'source' => 'siop',
                'scope' => 'nacional',
                'external_id' => 'dropdown-filtered-package-'.$index,
                'tracking_number' => $package['tracking'],
                'phone' => $package['phone'],
                'recipient' => 'Destinatario '.$index,
                'current_event' => $package['event'],
                'updated_at_api' => $package['date'],
            ]);
        }

        $creator = Livewire::test(PostalCampaignManager::class, [
            'scope' => 'nacional',
            'modalMode' => true,
        ])
            ->set('testMode', false)
            ->assertSee('Todos (3)')
            ->assertSee('Camino (2)')
            ->set('from', now()->subDays(3)->toDateString())
            ->assertSee('Todos (2)')
            ->assertSee('Camino (1)')
            ->set('eventFilter', 'En Camino')
            ->assertSet('limit', 1)
            ->assertSee('1 paquetes en En Camino para el rango seleccionado');
    }

    public function test_international_modal_filters_all_packages_by_event_and_selected_dates(): void
    {
        $this->actingAs(User::factory()->create());
        Queue::fake();

        $trackings = ['RR123456801XX', 'RR123456802XX', 'RR123456803XX', 'RR123456804XX'];
        $includedTrackings = array_slice($trackings, 0, 2);
        $packages = [
            ['event' => 'En aduana', 'date' => now()->subDays(2)],
            ['event' => 'En aduana', 'date' => now()->subDay()],
            ['event' => 'En aduana', 'date' => now()->subDays(6)],
            ['event' => 'Llegada a Bolivia', 'date' => now()->subDay()],
        ];

        foreach ($packages as $index => $package) {
            PostalPackage::create([
                'source' => 'sitra',
                'scope' => 'internacional',
                'external_id' => 'international-event-date-package-'.$index,
                'tracking_number' => $trackings[$index],
                'phone' => '+5917999200'.$index,
                'recipient' => 'Destinatario internacional '.$index,
                'current_event' => $package['event'],
                'updated_at_api' => $package['date'],
            ]);
        }

        $creator = Livewire::test(PostalCampaignManager::class, [
            'scope' => 'internacional',
            'modalMode' => true,
        ])
            ->assertSee('Eventos internacionales')
            ->assertDontSee('Eventos nacionales')
            ->assertSee('Aduana (3)')
            ->set('from', now()->subDays(3)->toDateString())
            ->set('to', now()->toDateString())
            ->assertSee('Todos (3)')
            ->assertSee('Aduana (2)')
            ->call('filterByEvent', 'Aduana')
            ->assertSet('limit', 2)
            ->assertSee('2 paquetes en Aduana para el rango seleccionado')
            ->set('campaignName', 'Campaña internacional por evento y fechas')
            ->call('prepareCampaign')
            ->assertDispatched('campaign-created');

        $campaign = PostalCampaign::where('name', 'Campaña internacional por evento y fechas')->firstOrFail();
        $this->assertSame(2, $campaign->total);
        $this->assertEqualsCanonicalizing(
            $includedTrackings,
            $campaign->messages()->with('package')->get()->pluck('package.tracking_number')->all(),
        );
    }

    public function test_date_filter_finds_national_and_international_packages_with_phone_in_payload(): void
    {
        $this->actingAs(User::factory()->create());
        Queue::fake();

        foreach ([
            ['scope' => 'nacional', 'source' => 'siop', 'inside' => 'NA123456795', 'outside' => 'NA123456796'],
            ['scope' => 'internacional', 'source' => 'sitra', 'inside' => 'RR123456795XX', 'outside' => 'RR123456796XX'],
        ] as $case) {
            $inside = PostalPackage::create([
                'source' => $case['source'],
                'scope' => $case['scope'],
                'external_id' => 'payload-phone-in-range-'.$case['scope'],
                'tracking_number' => $case['inside'],
                'current_event' => 'Recibido Origen',
                'updated_at_api' => null,
                'raw_payload' => ['destinatario' => ['telefono' => '+59170000991']],
            ]);
            $inside->events()->create([
                'source_event' => 'received',
                'event_name' => 'Recibido Origen',
                'event_at' => now()->subDay(),
            ]);

            $outside = PostalPackage::create([
                'source' => $case['source'],
                'scope' => $case['scope'],
                'external_id' => 'payload-phone-out-of-range-'.$case['scope'],
                'tracking_number' => $case['outside'],
                'current_event' => 'Recibido Origen',
                'updated_at_api' => null,
                'raw_payload' => ['destinatario' => ['telefono' => '+59170000992']],
            ]);
            $outside->events()->create([
                'source_event' => 'received',
                'event_name' => 'Recibido Origen',
                'event_at' => now()->subDays(10),
            ]);

            $creator = Livewire::test(PostalCampaignManager::class, [
                'scope' => $case['scope'],
                'modalMode' => true,
            ])
                ->set('testMode', false)
                ->set('from', now()->subDays(2)->toDateString())
                ->set('to', now()->toDateString())
                ->assertSet('limit', 1)
                ->assertSee('Todos (1)')
                ->set('campaignName', 'Campaña con teléfono '.$case['scope'])
                ->call('prepareCampaign')
                ->assertDispatched('campaign-created');

            $campaign = PostalCampaign::where('name', 'Campaña con teléfono '.$case['scope'])->firstOrFail();
            $this->assertSame(1, $campaign->total);
            $this->assertSame('+59170000991', $campaign->messages()->value('phone'));
        }
    }

    public function test_modal_event_counts_and_campaign_packages_respect_the_selected_date_range(): void
    {
        $this->actingAs(User::factory()->create());
        $trackings = ['NA123456785', 'NA123456786', 'NA123456787', 'NA123456788'];
        $includedTrackings = array_slice($trackings, 0, 2);
        $createdAt = [
            now()->subDays(2),
            now()->subDay(),
            now()->subDays(5),
            now()->subDay(),
        ];
        $events = ['En camino', 'En camino', 'En camino', 'Recibido Origen'];

        foreach ($trackings as $index => $trackingNumber) {
            PostalPackage::create([
                'source' => 'siop',
                'scope' => 'nacional',
                'external_id' => 'date-range-event-package-'.$index,
                'tracking_number' => $trackingNumber,
                'phone' => '+5917000010'.$index,
                'recipient' => 'Destinatario '.$index,
                'current_event' => $events[$index],
                'updated_at_api' => $createdAt[$index],
            ]);
        }

        $creator = Livewire::test(PostalCampaignManager::class, [
            'scope' => 'nacional',
            'modalMode' => true,
        ])
            ->set('testMode', false)
            ->set('from', now()->subDays(3)->toDateString())
            ->set('to', now()->toDateString())
            ->assertSee('Todos (3)')
            ->assertSee('Camino (2)')
            ->set('eventFilter', 'En Camino')
            ->assertSet('limit', 2)
            ->assertSee('2 paquetes en En Camino para el rango seleccionado')
            ->set('limit', 20)
            ->assertSet('limit', 2)
            ->set('campaignName', 'Campaña por evento y fechas')
            ->call('prepareCampaign')
            ->assertDispatched('campaign-created');

        $campaign = PostalCampaign::where('name', 'Campaña por evento y fechas')->firstOrFail();
        $this->assertSame(2, $campaign->total);
        $this->assertEqualsCanonicalizing(
            $includedTrackings,
            $campaign->messages()->with('package')->get()->pluck('package.tracking_number')->all(),
        );
    }

    public function test_national_campaign_list_preserves_recipient_fallback_from_package_payload(): void
    {
        $this->actingAs(User::factory()->create());
        PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'campaign-payload-fallback',
            'tracking_number' => 'NA123456780',
            'phone' => '+59170000001',
            'current_event' => 'En proceso',
            'raw_payload' => [
                'declaracion_cds' => [
                    'objetos' => [[
                        'declaraciones' => [[
                            'destinatario_declarado' => ['nombre' => 'Destinatario desde payload'],
                        ]],
                    ]],
                ],
            ],
        ]);

        Livewire::test(PostalCampaignManager::class, ['scope' => 'nacional'])
            ->set('testMode', false)
            ->call('applyFilters')
            ->assertSee('Destinatario desde payload');
    }

    public function test_national_campaign_summary_classifies_using_only_the_latest_package_event(): void
    {
        $this->actingAs(User::factory()->create());
        $package = PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'campaign-latest-event',
            'tracking_number' => 'NA123456781',
            'phone' => '+59170000001',
            'current_event' => 'En Camino',
            'updated_at_api' => now(),
        ]);
        $package->events()->create([
            'source_event' => '312',
            'event_name' => 'Disponible en oficina',
            'event_at' => now()->subDay(),
        ]);
        $package->events()->create([
            'source_event' => 'other',
            'event_name' => 'En tránsito',
            'event_at' => now(),
        ]);

        Livewire::test(PostalCampaignManager::class, ['scope' => 'nacional'])
            ->assertSee('En Camino (1)')
            ->assertSee('Ventanilla para recojo (0)');
    }

    public function test_international_campaign_filters_queue_cds_sync_without_waiting_for_it(): void
    {
        $this->actingAs(User::factory()->create());
        PostalPackage::create([
            'source' => 'sitra',
            'scope' => 'internacional',
            'external_id' => 'international-cds-async',
            'tracking_number' => 'RR123456789XX',
            'phone' => '+59170000001',
            'current_event' => 'En proceso',
            'updated_at_api' => now(),
        ]);
        Queue::fake();

        Livewire::test(PostalCampaignManager::class, ['scope' => 'internacional'])
            ->set('testMode', false)
            ->call('applyFilters')
            ->assertSee('La lista ya está disponible')
            ->assertSee('RR123456789XX');

        Queue::assertPushed(
            SyncCdsDeclarationsForCampaign::class,
            fn (SyncCdsDeclarationsForCampaign $job): bool => $job->trackings === ['RR123456789XX']
                && $job->connection === 'database',
        );
    }

    public function test_international_campaign_does_not_queue_already_synced_declarations(): void
    {
        $this->actingAs(User::factory()->create());
        PostalPackage::create([
            'source' => 'sitra',
            'scope' => 'internacional',
            'external_id' => 'international-cds-already-synced',
            'tracking_number' => 'RR123456788XX',
            'phone' => '+59170000002',
            'current_event' => 'En proceso',
            'cds_synced_at' => now(),
            'updated_at_api' => now(),
        ]);
        Queue::fake();

        Livewire::test(PostalCampaignManager::class, ['scope' => 'internacional'])
            ->set('testMode', false)
            ->call('applyFilters')
            ->assertDontSee('La lista ya está disponible');

        Queue::assertNothingPushed();
    }

    public function test_it_sends_a_campaign_message_to_the_package_phone_after_confirmation(): void
    {
        config([
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.api_version' => 'v23.0',
            'services.whatsapp.ca_bundle' => null,
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.campaign.1']]], 200),
        ]);

        [$campaign, $campaignMessage] = $this->createPreparedCampaign('nacional', false);

        Livewire::test(PostalCampaignManager::class, ['scope' => 'nacional'])
            ->set('testMode', false)
            ->call('sendCampaign', $campaign->id)
            ->assertSee('Envío finalizado: 1 enviado(s), 0 fallido(s).');

        Http::assertSent(fn ($request) => $request['to'] === '59170000001'
            && $request['text']['body'] === 'Hola Destinatario de prueba, guía NA123456789');
        $this->assertDatabaseHas('postal_campaign_messages', [
            'id' => $campaignMessage->id,
            'phone' => '+59170000001',
            'status' => 'sent',
            'provider_message_id' => 'wamid.campaign.1',
        ]);
        $this->assertDatabaseHas('postal_campaigns', [
            'id' => $campaign->id,
            'status' => 'sent',
            'sent' => 1,
            'pending' => 0,
        ]);
    }

    public function test_chat_shows_campaign_messages_and_replies_in_one_package_timeline(): void
    {
        [$campaign, $campaignMessage] = $this->createPreparedCampaign('nacional', false);
        $campaignMessage->update([
            'status' => 'sent',
            'provider_message_id' => 'wamid.chat.campaign',
            'sent_at' => now()->subMinute(),
        ]);
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.chat.package-reply',
            'phone' => '59170000001',
            'contact_name' => 'Destinatario de prueba',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => '¿Dónde está mi paquete?',
            'status' => 'received',
            'received_at' => now(),
        ]);

        $chat = Livewire::test(Chat::class)
            ->call('selectCampaignMessage', $campaignMessage->id)
            ->assertSet('tab', 'campaigns')
            ->assertSet('selectedCampaignMessageId', $campaignMessage->id)
            ->assertSee('Campaña preparada')
            ->assertSee('Hola Destinatario de prueba, guía NA123456789')
            ->assertSee('¿Dónde está mi paquete?');

        $this->assertLessThan(
            strpos($chat->html(), '¿Dónde está mi paquete?'),
            strpos($chat->html(), 'Hola Destinatario de prueba, guía NA123456789'),
            'La respuesta del destinatario debe aparecer después del mensaje de campaña.',
        );
    }

    public function test_test_mode_sends_only_to_registered_package_numbers(): void
    {
        config([
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.api_version' => 'v23.0',
            'services.whatsapp.test_recipient' => '',
            'services.whatsapp.ca_bundle' => null,
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.campaign.test']]], 200),
        ]);

        [$campaign] = $this->createPreparedCampaign('nacional', true);

        Livewire::test(PostalCampaignManager::class, ['scope' => 'nacional'])
            ->call('sendCampaign', $campaign->id)
            ->assertSee('Envío finalizado: 1 enviado(s), 0 fallido(s).');

        Http::assertSent(fn ($request) => $request['to'] === '59170000001');
        $this->assertDatabaseHas('postal_campaign_messages', [
            'postal_campaign_id' => $campaign->id,
            'phone' => '+59170000001',
            'status' => 'sent',
        ]);
    }

    public function test_it_can_add_and_persist_test_numbers(): void
    {
        config(['services.whatsapp.test_recipient' => '64229403']);
        $this->actingAs(User::factory()->create());

        $this->get(route('whatsapp.test-numbers'))
            ->assertOk()
            ->assertSee('Números de prueba')
            ->assertSee('Usuarios');

        Livewire::test(WhatsAppTestNumbers::class)
            ->set('newTestName', 'Celular de Quique')
            ->set('newTestNumber', '+591 7123-4567')
            ->call('addTestNumber')
            ->assertHasNoErrors()
            ->assertSee('59171234567')
            ->assertSee('Celular de Quique')
            ->assertSee('Contacto de prueba guardado.');

        $numbers = json_decode((string) DB::table('app_settings')->where('key', 'whatsapp_test_numbers')->value('value'), true);
        $this->assertContains(['name' => 'Celular de Quique', 'number' => '59171234567'], $numbers);

        $this->assertTrue(app(\App\Services\WhatsAppTestNumberRegistry::class)->contains('59171234567'));
    }

    public function test_legacy_test_numbers_remain_usable_and_show_a_fallback_name(): void
    {
        config(['services.whatsapp.test_recipient' => '']);
        DB::table('app_settings')->insert([
            'key' => 'whatsapp_test_numbers',
            'value' => json_encode(['59171234567']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test(WhatsAppTestNumbers::class)
            ->assertSee('Número existente')
            ->assertSee('59171234567')
            ->call('editTestName', '59171234567')
            ->set('editingTestName', 'Teléfono del equipo')
            ->call('saveTestName')
            ->assertSee('Teléfono del equipo');

        $this->assertTrue(app(\App\Services\WhatsAppTestNumberRegistry::class)->contains('59171234567'));
        $this->assertSame(
            [['name' => 'Teléfono del equipo', 'number' => '59171234567']],
            json_decode((string) DB::table('app_settings')->where('key', 'whatsapp_test_numbers')->value('value'), true),
        );
    }

    public function test_campaign_test_mode_can_use_a_registered_test_number(): void
    {
        config([
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.api_version' => 'v23.0',
            'services.whatsapp.test_recipient' => '64229403',
            'services.whatsapp.ca_bundle' => null,
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.campaign.custom-test']]], 200),
        ]);
        $this->actingAs(User::factory()->create());
        DB::table('app_settings')->insert([
            'key' => 'whatsapp_test_numbers',
            'value' => json_encode(['59171234567']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        [$campaign, $campaignMessage] = $this->createPreparedCampaign('nacional', true, '59171234567');

        Livewire::test(PostalCampaignManager::class, ['scope' => 'nacional'])
            ->call('sendCampaign', $campaign->id)
            ->assertSee('Envío finalizado: 1 enviado(s), 0 fallido(s).');

        Http::assertSent(fn ($request) => $request['to'] === '59171234567');
        Http::assertNotSent(fn ($request) => $request['to'] === '59170000001');
        $this->assertDatabaseHas('postal_campaign_messages', [
            'id' => $campaignMessage->id,
            'phone' => '59171234567',
        ]);
    }

    public function test_test_mode_campaign_includes_only_packages_with_registered_numbers(): void
    {
        config(['services.whatsapp.test_recipient' => '']);
        DB::table('app_settings')->insert([
            'key' => 'whatsapp_test_numbers',
            'value' => json_encode([['name' => 'Prueba', 'number' => '59171111111']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach ([
            ['registered', '+591 7111-1111'],
            ['unregistered', '+591 7222-2222'],
        ] as [$id, $phone]) {
            PostalPackage::create([
                'source' => 'siop',
                'scope' => 'nacional',
                'external_id' => 'test-allowlist-'.$id,
                'tracking_number' => 'NA'.$id,
                'recipient' => 'Destinatario '.$id,
                'current_event' => 'En proceso',
                'phone' => $phone,
                'updated_at_api' => now(),
            ]);
        }

        Livewire::test(PostalCampaignManager::class, ['scope' => 'nacional'])
            ->assertSet('testMode', true)
            ->assertSee('Crear campaña')
            ->assertDontSee('Enviar prueba a')
            ->set('campaignName', 'Solo números autorizados')
            ->call('prepareCampaign')
            ->assertSee('Campaña preparada con 1 paquetes.');

        $campaign = PostalCampaign::where('name', 'Solo números autorizados')->firstOrFail();
        $this->assertSame(1, $campaign->total);
        $this->assertDatabaseHas('postal_campaign_messages', [
            'postal_campaign_id' => $campaign->id,
            'phone' => '+591 7111-1111',
            'postal_package_id' => PostalPackage::where('external_id', 'test-allowlist-registered')->value('id'),
        ]);
        $this->assertDatabaseMissing('postal_campaign_messages', [
            'postal_campaign_id' => $campaign->id,
            'phone' => '+591 7222-2222',
        ]);
    }

    public function test_test_mode_send_skips_a_package_removed_from_test_number_registry(): void
    {
        [$campaign, $message] = $this->createPreparedCampaign('nacional', true);
        DB::table('app_settings')->where('key', 'whatsapp_test_numbers')->update(['value' => json_encode([])]);
        Http::fake();

        Livewire::test(PostalCampaignManager::class, ['scope' => 'nacional'])
            ->call('sendCampaign', $campaign->id)
            ->assertSee('Envío finalizado: 0 enviado(s), 1 fallido(s).');

        Http::assertNothingSent();
        $this->assertDatabaseHas('postal_campaign_messages', [
            'id' => $message->id,
            'status' => 'failed',
            'error_message' => 'El teléfono del paquete ya no está registrado en Números de prueba.',
        ]);
    }

    public function test_test_mode_sends_to_each_matching_package_and_excludes_other_numbers(): void
    {
        config([
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.api_version' => 'v23.0',
            'services.whatsapp.test_recipient' => '',
            'services.whatsapp.ca_bundle' => null,
        ]);
        DB::table('app_settings')->insert([
            'key' => 'whatsapp_test_numbers',
            'value' => json_encode([
                ['name' => 'Prueba 1', 'number' => '59171111111'],
                ['name' => 'Prueba 2', 'number' => '59172222222'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.allowlist.1']]], 200)
                ->push(['messages' => [['id' => 'wamid.allowlist.2']]], 200),
        ]);
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach ([
            ['registered-1', '+591 7111-1111'],
            ['registered-2', '591-7222-2222'],
            ['unregistered', '+591 7333-3333'],
        ] as [$id, $phone]) {
            PostalPackage::create([
                'source' => 'siop',
                'scope' => 'nacional',
                'external_id' => 'test-send-allowlist-'.$id,
                'tracking_number' => 'NA'.$id,
                'recipient' => 'Destinatario '.$id,
                'current_event' => 'En proceso',
                'phone' => $phone,
                'updated_at_api' => now(),
            ]);
        }

        $component = Livewire::test(PostalCampaignManager::class, ['scope' => 'nacional'])
            ->assertSet('testMode', true)
            ->set('campaignName', 'Envío a pruebas registradas')
            ->call('prepareCampaign')
            ->assertSee('Campaña preparada con 2 paquetes.');

        $campaign = PostalCampaign::where('name', 'Envío a pruebas registradas')->firstOrFail();
        $this->assertSame(2, $campaign->total);
        $campaign->update(['interval_seconds' => 1]);

        $component->call('sendCampaign', $campaign->id)
            ->assertSee('Envío finalizado: 2 enviado(s), 0 fallido(s).');

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['to'] === '59171111111');
        Http::assertSent(fn ($request) => $request['to'] === '59172222222');
        Http::assertNotSent(fn ($request) => $request['to'] === '59173333333');
    }

    public function test_campaign_registry_can_send_pending_messages_to_package_numbers(): void
    {
        config([
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.api_version' => 'v23.0',
            'services.whatsapp.ca_bundle' => null,
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.registry.pending']]], 200),
        ]);
        [$campaign, $message] = $this->createPreparedCampaign('nacional', false);

        Livewire::test(PostalCampaignRegistry::class, ['scope' => 'nacional'])
            ->assertSee('Enviar pendientes')
            ->call('sendCampaign', $campaign->id)
            ->assertSee('Envío iniciado')
            ->call('processNextCampaignMessage')
            ->assertSee('Envío finalizado: 1 enviado(s), 0 fallido(s).');

        Http::assertSent(fn ($request) => $request['to'] === '59170000001');
        $this->assertDatabaseHas('postal_campaign_messages', [
            'id' => $message->id,
            'status' => 'sent',
            'provider_message_id' => 'wamid.registry.pending',
        ]);
    }

    public function test_campaign_progress_opens_live_details_and_processes_messages_one_by_one(): void
    {
        config([
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.api_version' => 'v23.0',
            'services.whatsapp.ca_bundle' => null,
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.progress.1']]], 200)
                ->push(['messages' => [['id' => 'wamid.progress.2']]], 200)
                ->push(['messages' => [['id' => 'wamid.progress.3']]], 200),
        ]);
        [$campaign, $message] = $this->createPreparedCampaign('nacional', false);
        $campaign->update(['total' => 3, 'pending' => 3, 'interval_seconds' => 0]);
        for ($index = 1; $index < 3; $index++) {
            $package = PostalPackage::create([
                'source' => 'siop',
                'scope' => 'nacional',
                'external_id' => 'live-progress-package-'.$index,
                'tracking_number' => 'NALIVE'.$index,
                'recipient' => 'Destinatario '.$index,
                'phone' => '+5917000000'.($index + 2),
                'current_event' => 'En proceso',
            ]);
            $campaign->messages()->create([
                'postal_package_id' => $package->id,
                'phone' => $package->phone,
                'message' => 'Mensaje de prueba '.$index,
                'status' => 'pending',
            ]);
        }

        $component = Livewire::test(PostalCampaignRegistry::class, ['scope' => 'nacional'])
            ->assertSee('registry-progress-trigger', false)
            ->call('openCampaignInfo', $campaign->id)
            ->assertSee('registryCampaignInfo')
            ->assertDontSee($message->package->tracking_number)
            ->assertDontSee('NALIVE1')
            ->assertDontSee('NALIVE2')
            ->assertSee('0/3');

        $component->call('sendCampaign', $campaign->id)
            ->assertSee('Envío iniciado. Los mensajes se procesarán uno por uno y el progreso se actualizará automáticamente.')
            ->assertSee('wire:poll.500ms="processNextCampaignMessage"', false);
        $this->assertDatabaseHas('postal_campaigns', [
            'id' => $campaign->id,
            'status' => 'sending',
            'pending' => 3,
        ]);

        foreach ([1, 2, 3] as $sent) {
            $component->call('processNextCampaignMessage')
                ->assertSee('registry-info-overlay', false)
                ->assertSee($sent.'/3');
            if ($sent === 1) {
                $component->assertSee($message->package->tracking_number)
                    ->assertSee('Enviado')
                    ->assertDontSee('NALIVE1')
                    ->assertDontSee('NALIVE2');
            } elseif ($sent === 2) {
                $component->assertSee('NALIVE1')
                    ->assertSee('Enviado')
                    ->assertDontSee('NALIVE2');
            } else {
                $component->assertSee('NALIVE2');
            }
        }

        $component
            ->assertSee('NALIVE1')
            ->assertSee('NALIVE2')
            ->assertSee('0 pendientes')
            ->assertSee('Envío finalizado: 3 enviado(s), 0 fallido(s).');
        $component->assertSee('registry-message-appear', false);
        $this->assertDatabaseHas('postal_campaigns', [
            'id' => $campaign->id,
            'status' => 'sent',
            'sent' => 3,
            'pending' => 0,
        ]);
        Http::assertSentCount(3);
    }

    public function test_campaign_details_label_failed_messages_in_spanish_with_the_error(): void
    {
        [$campaign, $message] = $this->createPreparedCampaign('nacional', false);
        $message->update([
            'status' => 'failed',
            'error_message' => 'WhatsApp rechazó el mensaje.',
        ]);
        $campaign->update(['status' => 'failed', 'pending' => 0, 'failed' => 1]);

        Livewire::test(PostalCampaignRegistry::class, ['scope' => 'nacional'])
            ->call('openCampaignInfo', $campaign->id)
            ->assertSee('No se pudo enviar')
            ->assertSee('WhatsApp rechazó el mensaje.');
    }

    public function test_campaign_messages_are_spread_over_the_configured_duration(): void
    {
        config([
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.api_version' => 'v23.0',
            'services.whatsapp.ca_bundle' => null,
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.paced']]], 200),
        ]);
        [$campaign] = $this->createPreparedCampaign('nacional', false);

        for ($index = 0; $index < 2; $index++) {
            $package = PostalPackage::create([
                'source' => 'siop',
                'scope' => 'nacional',
                'external_id' => 'paced-campaign-package-'.$index,
                'tracking_number' => 'NAPACED'.$index,
                'recipient' => 'Destinatario '.$index,
                'phone' => '+5917000000'.($index + 2),
                'current_event' => 'En proceso',
            ]);
            $campaign->messages()->create([
                'postal_package_id' => $package->id,
                'phone' => $package->phone,
                'message' => 'Mensaje de prueba '.$index,
                'status' => 'pending',
            ]);
        }

        $campaign->update([
            'total' => 3,
            'pending' => 3,
            'interval_seconds' => 1,
            'status' => 'sending',
        ]);
        $startedAt = microtime(true);

        (new SendPostalCampaignMessages($campaign->id))->handle(
            app(\App\Services\WhatsAppCloudApiService::class),
            app(\App\Services\WhatsAppTestNumberRegistry::class),
        );

        $this->assertGreaterThanOrEqual(0.65, microtime(true) - $startedAt);
        $this->assertDatabaseHas('postal_campaigns', [
            'id' => $campaign->id,
            'sent' => 3,
            'pending' => 0,
            'status' => 'sent',
        ]);
        Http::assertSentCount(3);
    }

    public function test_campaign_registry_retries_failed_messages_without_resending_successful_ones(): void
    {
        config([
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.api_version' => 'v23.0',
            'services.whatsapp.ca_bundle' => null,
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'Temporal API failure']], 500)
                ->push(['messages' => [['id' => 'wamid.registry.retried']]], 200),
        ]);
        [$campaign, $failedMessage] = $this->createPreparedCampaign('nacional', false);
        $successfulPackage = PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'registry-already-sent-package',
            'tracking_number' => 'NAALREADYSENT',
            'recipient' => 'Destinatario ya enviado',
            'phone' => '+59170000002',
            'current_event' => 'En proceso',
        ]);
        $successfulMessage = PostalCampaignMessage::create([
            'postal_campaign_id' => $campaign->id,
            'postal_package_id' => $successfulPackage->id,
            'phone' => '+59170000002',
            'message' => 'Mensaje que ya se envió',
            'status' => 'sent',
            'provider_message_id' => 'wamid.registry.already-sent',
            'sent_at' => now(),
        ]);
        $campaign->update(['total' => 2, 'sent' => 1, 'failed' => 0, 'pending' => 1, 'status' => 'partial']);

        $campaign->update(['interval_seconds' => 0]);
        $component = Livewire::test(PostalCampaignRegistry::class, ['scope' => 'nacional'])
            ->assertSee('Enviar pendientes')
            ->call('sendCampaign', $campaign->id)
            ->call('processNextCampaignMessage')
            ->assertSee('Envío finalizado: 1 enviado(s), 1 fallido(s).')
            ->assertSee('Reintentar fallidos');

        $component->call('retryFailedCampaign', $campaign->id)
            ->call('processNextCampaignMessage')
            ->assertSee('Envío finalizado: 2 enviado(s), 0 fallido(s).');

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['to'] === '59170000001');
        Http::assertNotSent(fn ($request) => $request['text']['body'] === 'Mensaje que ya se envió');
        $this->assertDatabaseHas('postal_campaign_messages', [
            'id' => $failedMessage->id,
            'status' => 'sent',
            'provider_message_id' => 'wamid.registry.retried',
        ]);
        $this->assertDatabaseHas('postal_campaign_messages', [
            'id' => $successfulMessage->id,
            'status' => 'sent',
            'provider_message_id' => 'wamid.registry.already-sent',
        ]);
        $this->assertDatabaseHas('postal_campaigns', [
            'id' => $campaign->id,
            'sent' => 2,
            'failed' => 0,
            'pending' => 0,
            'status' => 'sent',
        ]);
    }

    public function test_campaign_registry_cancels_pending_messages_and_preserves_sent_ones(): void
    {
        [$campaign, $pendingMessage] = $this->createPreparedCampaign('nacional', false);
        $sentPackage = PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'registry-cancel-sent-package',
            'tracking_number' => 'NACANCELSENT',
            'recipient' => 'Destinatario ya enviado',
            'phone' => '+59170000002',
            'current_event' => 'En proceso',
        ]);
        $sentMessage = PostalCampaignMessage::create([
            'postal_campaign_id' => $campaign->id,
            'postal_package_id' => $sentPackage->id,
            'phone' => '+59170000002',
            'message' => 'Ya enviado',
            'status' => 'sent',
            'provider_message_id' => 'wamid.registry.cancel.sent',
            'sent_at' => now(),
        ]);
        $campaign->update(['total' => 2, 'sent' => 1, 'pending' => 1, 'status' => 'partial']);

        Livewire::test(PostalCampaignRegistry::class, ['scope' => 'nacional'])
            ->assertSee('Cancelar envíos')
            ->call('cancelCampaign', $campaign->id)
            ->assertSee('Envíos pendientes cancelados. Los mensajes ya enviados se conservaron.')
            ->assertSee('Cancelled');

        $this->assertDatabaseHas('postal_campaign_messages', [
            'id' => $pendingMessage->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('postal_campaign_messages', [
            'id' => $sentMessage->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('postal_campaigns', [
            'id' => $campaign->id,
            'status' => 'cancelled',
            'pending' => 0,
            'sent' => 1,
        ]);
    }

    public function test_campaign_registry_can_cancel_a_queued_send_before_messages_are_sent(): void
    {
        [$campaign, $message] = $this->createPreparedCampaign('nacional', false);
        Queue::fake();

        $component = Livewire::test(PostalCampaignRegistry::class, ['scope' => 'nacional'])
            ->assertSee('Cancelar envíos')
            ->assertDontSee('Editar')
            ->assertDontSee('Información')
            ->call('sendCampaign', $campaign->id)
            ->assertSee('Envío iniciado. Los mensajes se procesarán uno por uno y el progreso se actualizará automáticamente.')
            ->call('cancelCampaign', $campaign->id)
            ->assertSee('Envíos pendientes cancelados. Los mensajes ya enviados se conservaron.');

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('postal_campaign_messages', [
            'id' => $message->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('postal_campaigns', [
            'id' => $campaign->id,
            'status' => 'cancelled',
            'pending' => 0,
        ]);

        Http::fake();
        (new SendPostalCampaignMessages($campaign->id))->handle(
            app(\App\Services\WhatsAppCloudApiService::class),
            app(\App\Services\WhatsAppTestNumberRegistry::class),
        );
        Http::assertNothingSent();
    }

    public function test_campaign_registry_deletes_campaign_and_cascades_its_messages(): void
    {
        [$campaign, $message] = $this->createPreparedCampaign('nacional', false);

        Livewire::test(PostalCampaignRegistry::class, ['scope' => 'nacional'])
            ->assertSee('Eliminar')
            ->call('deleteCampaign', $campaign->id)
            ->assertSee('Campaña y mensajes asociados eliminados.')
            ->assertDontSee('Campaña preparada');

        $this->assertDatabaseMissing('postal_campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseMissing('postal_campaign_messages', ['id' => $message->id]);
    }

    private function assertCampaignCanBeCreated(string $scope, array $phoneFields): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        PostalPackage::create(array_merge([
            'source' => $scope === 'nacional' ? 'siop' : 'sitra',
            'scope' => $scope,
            'external_id' => 'test-'.$scope,
            'tracking_number' => $scope === 'nacional' ? 'NA123456789' : 'EE123456789AR',
            'recipient' => 'Destinatario de prueba',
            'current_event' => 'En proceso',
        ], $phoneFields));
        DB::table('app_settings')->updateOrInsert(
            ['key' => 'whatsapp_test_numbers'],
            [
                'value' => json_encode([[
                    'name' => 'Prueba',
                    'number' => preg_replace('/\D+/', '', $phoneFields['cds_phone'] ?? $phoneFields['phone']),
                ]]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        Livewire::test(PostalCampaignManager::class, ['scope' => $scope])
            ->assertSee('Crear campaña')
            ->set('campaignName', 'Campaña '.$scope)
            ->call('prepareCampaign')
            ->assertHasNoErrors()
            ->assertSee('Campaña preparada con 1 paquetes.');

        $campaign = PostalCampaign::where('name', 'Campaña '.$scope)->firstOrFail();
        $this->assertSame($scope, $campaign->filters['scope']);
        $this->assertDatabaseHas('postal_campaign_messages', [
            'postal_campaign_id' => $campaign->id,
            'phone' => $phoneFields['cds_phone'] ?? $phoneFields['phone'],
            'postal_package_id' => PostalPackage::where('external_id', 'test-'.$scope)->value('id'),
        ]);
    }

    private function createPreparedCampaign(string $scope, bool $testMode, string $phone = '+59170000001'): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $package = PostalPackage::create([
            'source' => 'siop',
            'scope' => $scope,
            'external_id' => 'send-'.$scope.'-'.$testMode,
            'tracking_number' => 'NA123456789',
            'recipient' => 'Destinatario de prueba',
            'phone' => $phone,
            'current_event' => 'En proceso',
        ]);
        $campaign = PostalCampaign::create([
            'user_id' => $user->id,
            'name' => 'Campaña preparada',
            'filters' => ['scope' => $scope],
            'events' => [],
            'template' => 'Hola {recipient}, guía {tracking_number}',
            'interval_seconds' => 30,
            'test_mode' => $testMode,
            'status' => 'prepared',
            'total' => 1,
            'pending' => 1,
        ]);
        if ($testMode) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => 'whatsapp_test_numbers'],
                [
                    'value' => json_encode([['name' => 'Prueba', 'number' => preg_replace('/\D+/', '', $phone)]]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
        $message = PostalCampaignMessage::create([
            'postal_campaign_id' => $campaign->id,
            'postal_package_id' => $package->id,
            'phone' => $phone,
            'message' => 'Hola Destinatario de prueba, guía NA123456789',
            'status' => 'pending',
        ]);

        return [$campaign, $message];
    }
}
