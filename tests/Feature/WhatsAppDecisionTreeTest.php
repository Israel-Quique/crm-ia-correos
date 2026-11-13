<?php

namespace Tests\Feature;

use App\Models\AutomaticResponse;
use App\Models\PostalPackage;
use App\Models\User;
use App\Models\WhatsAppDecisionTreeSession;
use App\Services\AutomaticResponseMatcher;
use App\Services\WhatsAppDecisionTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use App\Livewire\AutomaticDecisionTrees;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class WhatsAppDecisionTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_tree_collects_delivery_details_and_uses_the_verified_tariff(): void
    {
        config(['services.delivery_express.url' => 'https://rates.test/delivery']);
        Http::fake([
            'rates.test/*' => Http::response([
                [
                    'peso1' => '15.00',
                    'min_weight_kg' => 0,
                    'max_weight_kg' => 5,
                    'origen' => ['nombre_origen' => 'LA PAZ'],
                    'destino' => ['nombre_destino' => 'SANTA CRUZ'],
                    'servicio_extra' => ['nombre' => 'Puerta a puerta'],
                ],
            ]),
        ]);

        $flow = app(WhatsAppDecisionTreeService::class);
        $response = app(AutomaticResponseMatcher::class)->match('Quiero cotizar Delivery Express');
        $phone = '15551234567';

        $opening = $flow->replyTo($phone, 'Quiero cotizar Delivery Express', $response);
        $this->assertStringContainsString($response->template, $opening);
        $this->assertStringContainsString('¿Desde qué ciudad', $opening);
        $this->assertStringContainsString('¿A qué ciudad', $flow->replyTo($phone, 'La Paz'));
        $this->assertStringContainsString('¿Cuánto pesa', $flow->replyTo($phone, 'Santa Cruz'));
        $this->assertStringContainsString('¿Qué modalidad', $flow->replyTo($phone, '2 kg'));
        $quote = $flow->replyTo($phone, '1');

        $this->assertStringContainsString('tarifa verificada', $quote);
        $this->assertStringContainsString('Bs 15.00', $quote);
        $this->assertDatabaseMissing('whatsapp_decision_tree_sessions', ['phone' => $phone]);
        Http::assertSent(fn ($request) => $request->url() === 'https://rates.test/delivery');
    }

    public function test_tracking_tree_looks_up_the_guide_and_reports_its_current_status(): void
    {
        $phone = '15551234567';
        PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'tree-track-1',
            'tracking_number' => 'RR123456789XX',
            'current_event' => 'Disponible en ventanilla',
            'updated_at_api' => now(),
        ]);
        $response = app(AutomaticResponseMatcher::class)->match('¿Dónde está mi paquete?');
        $flow = app(WhatsAppDecisionTreeService::class);

        $opening = $flow->replyTo($phone, '¿Dónde está mi paquete?', $response);
        $this->assertStringContainsString($response->template, $opening);
        $this->assertStringContainsString('número completo de guía', $opening);
        $trackingReply = $flow->replyTo($phone, 'RR123456789XX');

        $this->assertStringContainsString('figura disponible para su recojo en ventanilla', $trackingReply);
        $this->assertDatabaseHas('whatsapp_decision_tree_sessions', [
            'phone' => $phone,
            'flow_key' => 'tracking',
            'step' => 'tracking_another',
        ]);
    }

    public function test_tracking_tree_uses_the_matching_event_template_for_the_current_status(): void
    {
        $phone = '15551234567';
        PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'tree-track-delivered',
            'tracking_number' => 'RR123456789YY',
            'current_event' => 'Entregado',
            'updated_at_api' => now(),
        ]);
        $flow = app(WhatsAppDecisionTreeService::class);
        $response = app(AutomaticResponseMatcher::class)->match('¿Dónde está mi paquete?');
        $flow->replyTo($phone, '¿Dónde está mi paquete?', $response);

        $trackingReply = $flow->replyTo($phone, 'RR123456789YY');

        $this->assertStringContainsString(
            '✅ La guía RR123456789YY figura como entregada según el último evento registrado.',
            $trackingReply,
        );
        $this->assertStringNotContainsString('Guía RR123456789YY (nacional):', $trackingReply);
    }

    public function test_tracking_tree_uses_specific_international_courier_and_ventanilla_triggers(): void
    {
        $flow = app(WhatsAppDecisionTreeService::class);
        $matcher = app(AutomaticResponseMatcher::class);
        $trackingIntent = $matcher->match('Necesito seguimiento internacional');

        foreach ([
            ['15551230001', 'RR123456789AA', 'Asignado a cartero para reparto', 'fue asignada a un cartero'],
            ['15551230002', 'RR123456789BB', 'Disponible en ventanilla para recojo', 'disponible para su recojo en ventanilla'],
        ] as [$phone, $trackingNumber, $event, $expectedReply]) {
            PostalPackage::create([
                'source' => 'sitra',
                'scope' => 'internacional',
                'external_id' => 'tree-track-'.$trackingNumber,
                'tracking_number' => $trackingNumber,
                'current_event' => $event,
                'updated_at_api' => now(),
            ]);

            $flow->replyTo($phone, 'Necesito seguimiento internacional', $trackingIntent);
            $reply = $flow->replyTo($phone, $trackingNumber);

            $this->assertStringContainsString($expectedReply, $reply);
            $this->assertStringContainsString($trackingNumber, $reply);
        }
    }

    public function test_generic_quote_trigger_asks_scope_before_package_details(): void
    {
        $flow = app(WhatsAppDecisionTreeService::class);
        $message = 'Quisiera cotizar el envio de un paquete';
        $response = app(AutomaticResponseMatcher::class)->match($message);

        $this->assertSame('cotizacion_postal', $response?->key);

        $opening = $flow->replyTo('15551234567', $message, $response);

        $this->assertStringContainsString('1. Nacional (EMS NAT)', $opening);
        $this->assertStringContainsString('2. Internacional (EMS INT)', $opening);
        $this->assertStringContainsString('3. Delivery Express', $opening);

        foreach ([
            ['15551234568', 'Nacional', 'nacional', 'ems', '¿Desde qué ciudad de Bolivia'],
            ['15551234569', 'Internacional', 'internacional', 'ems', '¿A qué país se enviará'],
            ['15551234570', 'Delivery Express', 'nacional', 'delivery_express', '¿Desde qué ciudad de Bolivia'],
        ] as [$phone, $choice, $scope, $service, $expectedQuestion]) {
            $flow->replyTo($phone, $message, $response);
            $reply = $flow->replyTo($phone, $choice);
            $session = WhatsAppDecisionTreeSession::where('phone', $phone)->firstOrFail();

            $this->assertStringContainsString($expectedQuestion, $reply);
            $this->assertSame($scope, $session->context['scope']);
            $this->assertSame($service, $session->context['service']);
            $this->assertSame($scope === 'internacional' ? 'quote_destination' : 'quote_origin', $session->step);
        }
    }

    public function test_natural_send_request_starts_quote_and_keeps_cities_while_clarifying_service(): void
    {
        $message = 'Quiero mandar un pauqete de la paz a oruro';
        $response = app(AutomaticResponseMatcher::class)->match($message);
        $this->assertSame('cotizacion_postal', $response?->key);

        $flow = app(WhatsAppDecisionTreeService::class);
        $phone = '15551234571';
        $opening = $flow->replyTo($phone, $message, $response);
        $this->assertStringContainsString('3. Delivery Express', $opening);
        $fallbackOpening = $flow->replyTo('15551234572', $message);
        $this->assertStringContainsString('1. Nacional (EMS NAT)', $fallbackOpening);

        $clarification = $flow->replyTo($phone, 'En que cambian?');
        $this->assertStringContainsString('dentro de Bolivia', $clarification);
        $this->assertStringContainsString('hasta 5 kg', $clarification);
        $explanation = $flow->replyTo($phone, 'Me podrias explicar en que consisten?');
        $this->assertStringContainsString('para envíos desde Bolivia a otro país', $explanation);
        $this->assertStringContainsString('1. Nacional (EMS NAT)', $explanation);
        $pendingSession = WhatsAppDecisionTreeSession::where('phone', $phone)->firstOrFail();
        $this->assertSame('quote_scope', $pendingSession->step);
        $this->assertArrayHasKey('pending_national_route', $pendingSession->context);

        $reply = $flow->replyTo($phone, 'Nacional');
        $session = WhatsAppDecisionTreeSession::where('phone', $phone)->firstOrFail();
        $codes = app(\App\Services\PostarQuoteService::class)->destinationCodesFromText($message);

        $this->assertStringContainsString('¿Cuánto pesa el paquete?', $reply);
        $this->assertStringNotContainsString('¿Desde qué ciudad', $reply);
        $this->assertSame('quote_weight', $session->step);
        $this->assertSame($codes[0], $session->context['origin']);
        $this->assertSame($codes[array_key_last($codes)], $session->context['destination']);
    }

    public function test_quote_flow_understands_natural_route_and_delivery_mode_clarifications(): void
    {
        $flow = app(WhatsAppDecisionTreeService::class);
        $matcher = app(AutomaticResponseMatcher::class);
        $phone = '15551234573';
        $openingMessage = 'Quisiera enviar un pauquete';
        $response = $matcher->match($openingMessage);

        $this->assertSame('cotizacion_postal', $response?->key);
        $flow->replyTo($phone, $openingMessage, $response);

        $explanation = $flow->replyTo($phone, 'Me podira explicar en que cosiste sus envios?');
        $this->assertStringContainsString('Las diferencias son', $explanation);
        $this->assertSame('quote_scope', WhatsAppDecisionTreeSession::where('phone', $phone)->value('step'));

        $routeMessage = 'Quisiera mandar un pauquete de La Paz a Oruro';
        $servicePrompt = $flow->replyTo($phone, $routeMessage);
        $this->assertStringContainsString('EMS Nacional o Delivery Express', $servicePrompt);
        $routeCodes = app(\App\Services\PostarQuoteService::class)->destinationCodesFromText($routeMessage);
        $session = WhatsAppDecisionTreeSession::where('phone', $phone)->firstOrFail();
        $this->assertSame('quote_service', $session->step);
        $this->assertSame($routeCodes[0], $session->context['origin']);
        $this->assertSame($routeCodes[array_key_last($routeCodes)], $session->context['destination']);

        $flow->replyTo($phone, 'Delivery Express por favor');
        $flow->replyTo($phone, '4 kilos');
        $modeExplanation = $flow->replyTo($phone, 'En que varia?');

        $this->assertStringContainsString('Puerta a puerta', $modeExplanation);
        $this->assertStringContainsString('Ventanilla a ventanilla', $modeExplanation);
        $this->assertSame('quote_mode', WhatsAppDecisionTreeSession::where('phone', $phone)->value('step'));
    }

    public function test_generic_quote_phrase_completes_national_ems_flow_with_a_verified_tariff(): void
    {
        config(['services.postar.url' => 'https://postar.test/calculate']);
        Http::fake([
            'postar.test/*' => Http::response(['success' => true, 'tarifa' => 28.5]),
        ]);

        $message = 'Quisiera cotizar el envio de un paquete de La Paz a Oruro';
        $response = app(AutomaticResponseMatcher::class)->match($message);
        $this->assertSame('cotizacion_postal', $response?->key);

        $flow = app(WhatsAppDecisionTreeService::class);
        $phone = '15551234573';
        $opening = $flow->replyTo($phone, $message, $response);
        $this->assertStringContainsString('¿Qué tipo de envío deseas cotizar?', $opening);

        $selectionReply = $flow->replyTo($phone, 'Nacional');
        $this->assertStringContainsString('¿Cuánto pesa el paquete?', $selectionReply);
        $dimensionsPrompt = $flow->replyTo($phone, '2 kg');
        $this->assertStringContainsString('¿Deseas incluir dimensiones?', $dimensionsPrompt);
        $quote = $flow->replyTo($phone, 'no');

        $this->assertStringContainsString('EMS NAT', $quote);
        $this->assertStringContainsString('La Paz', $quote);
        $this->assertStringContainsString('Oruro', $quote);
        $this->assertStringContainsString('Bs 28.50', $quote);
        Http::assertSent(fn ($request) => $request->url() === 'https://postar.test/calculate'
            && $request['categoria'] === 'EMS NAT'
            && $request['destino'] === 'nacional_oruro'
            && $request['peso'] === 2.0);
    }

    public function test_international_ems_tree_uses_the_postar_tariff_with_optional_dimensions(): void
    {
        config(['services.postar.url' => 'https://postar.test/calculate']);
        Http::fake([
            'postar.test/*' => Http::response(['success' => true, 'tarifa' => 42.5]),
        ]);
        $flow = app(WhatsAppDecisionTreeService::class);
        $response = app(AutomaticResponseMatcher::class)->match('Quiero cotizar EMS internacional');
        $phone = '15551234567';

        $opening = $flow->replyTo($phone, 'Quiero cotizar EMS internacional', $response);
        $this->assertStringContainsString($response->template, $opening);
        $this->assertStringContainsString('Tomaré Bolivia como país de origen', $opening);
        $this->assertStringContainsString('¿A qué país', $opening);
        $this->assertStringContainsString('¿Cuánto pesa', $flow->replyTo($phone, 'Chile'));
        $this->assertStringContainsString('¿Deseas incluir dimensiones', $flow->replyTo($phone, '0.5 kg'));
        $quote = $flow->replyTo($phone, 'no');

        $this->assertStringContainsString('EMS INT', $quote);
        $this->assertStringContainsString('Chile', $quote);
        $this->assertStringContainsString('42.50', $quote);
        Http::assertSent(fn ($request) => $request->url() === 'https://postar.test/calculate'
            && $request['categoria'] === 'EMS INT'
            && $request['destino'] === 'Chile'
            && $request['peso'] === 0.5);
    }

    public function test_national_ems_tree_uses_the_saved_quote_template(): void
    {
        $flow = app(WhatsAppDecisionTreeService::class);
        $response = app(AutomaticResponseMatcher::class)->match('Quiero cotizar EMS nacional');

        $opening = $flow->replyTo('15551234567', 'Quiero cotizar EMS nacional', $response);

        $this->assertSame('EMS NAT', $response?->key);
        $this->assertStringContainsString($response->template, $opening);
        $this->assertStringContainsString('¿Desde qué ciudad de Bolivia', $opening);
    }

    public function test_menu_command_and_numeric_selection_start_tracking(): void
    {
        $phone = '15551234567';
        $flow = app(WhatsAppDecisionTreeService::class);

        $this->assertStringContainsString('1. Cotizar un envío', $flow->replyTo($phone, 'menu'));
        $this->assertStringContainsString('número completo de guía', $flow->replyTo($phone, '2'));
        $this->assertDatabaseHas('whatsapp_decision_tree_sessions', [
            'phone' => $phone,
            'flow_key' => 'tracking',
            'step' => 'tracking_number',
        ]);
    }

    public function test_decision_tree_has_a_separate_automation_page_and_toggle(): void
    {
        Livewire::test(AutomaticDecisionTrees::class)
            ->assertSee('Desarrollo de preguntas por nodos')
            ->assertSee('Simulador de chat en vivo')
            ->assertSee('DETONANTES ACTIVOS')
            ->assertSee('Cotizar un envío')
            ->assertSee('Rastrear un paquete')
            ->assertDontSee('ELIGE UNA RESPUESTA PARA AVANZAR EN EL ÁRBOL')
            ->assertDontSee('1. Cotizar un envío →')
            ->call('toggleDecisionFlow', 'quote')
            ->assertSee('Activar cotización');

        $this->assertDatabaseHas('app_settings', [
            'key' => 'automatic_decision_flow_quote',
            'value' => '0',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('automation'))
            ->assertOk()
            ->assertSee('Árboles de decisión')
            ->assertSee('Árbol de decisión');

        $this->get(route('automatic-responses'))
            ->assertOk()
            ->assertSee('Crear mensaje')
            ->assertDontSee('id="decision-tree-title"', false);
    }

    public function test_live_simulator_walks_quote_and_tracking_examples_without_external_actions(): void
    {
        $simulator = Livewire::test(AutomaticDecisionTrees::class)
            ->assertSet('simulatorNode', 'root')
            ->set('simulatorInput', 'Quiero cotizar EMS nacional')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_origin')
            ->set('simulatorInput', 'La Paz')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'Santa Cruz')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', '2 kg')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_dimensions')
            ->set('simulatorInput', 'No')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_terminal')
            ->assertSee('solo se envía un precio verificado');

        $simulator
            ->call('resetSimulator')
            ->set('simulatorInput', 'Necesito seguimiento internacional')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_number')
            ->set('simulatorInput', 'RR123456789XX')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_event')
            ->set('simulatorInput', 'ventanilla')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_terminal')
            ->assertSee('disponible para recojo en ventanilla');
    }

    public function test_simulator_starts_each_postal_flow_from_its_configured_trigger(): void
    {
        $flows = [
            ['Cotizar EMS nacional', 'quote_origin', ['scope' => 'nacional', 'service' => 'ems_nat']],
            ['Cotizar EMS internacional', 'quote_destination', ['scope' => 'internacional', 'service' => 'ems_int']],
            ['Cotizar Delivery Express', 'quote_origin', ['scope' => 'nacional', 'service' => 'delivery_express']],
            ['Quisiera cotizar el envio de un paquete', 'quote_scope', ['flow' => 'quote']],
            ['Quiero cotizar un envío', 'quote_scope', ['flow' => 'quote']],
            ['Consultar un envío nacional', 'tracking_number', ['flow' => 'tracking']],
            ['Consultar un envío internacional', 'tracking_number', ['flow' => 'tracking']],
        ];

        foreach ($flows as [$message, $expectedNode, $expectedContext]) {
            $simulator = Livewire::test(AutomaticDecisionTrees::class)
                ->set('simulatorInput', $message)
                ->call('sendSimulatorMessage')
                ->assertSet('simulatorNode', $expectedNode);

            foreach ($expectedContext as $key => $value) {
                $this->assertSame($value, $simulator->instance()->simulatorContext[$key]);
            }
        }
    }

    public function test_simulator_routes_the_three_generic_quote_choices(): void
    {
        foreach ([
            ['Nacional', 'nacional', 'ems_nat', 'quote_origin'],
            ['Internacional', 'internacional', 'ems_int', 'quote_destination'],
            ['Delivery Express', 'nacional', 'delivery_express', 'quote_origin'],
        ] as [$choice, $scope, $service, $node]) {
            $simulator = Livewire::test(AutomaticDecisionTrees::class)
                ->set('simulatorInput', 'Quisiera cotizar un envío de paquete')
                ->call('sendSimulatorMessage')
                ->assertSet('simulatorNode', 'quote_scope')
                ->assertSee('1. Nacional (EMS NAT)')
                ->set('simulatorInput', $choice)
                ->call('sendSimulatorMessage')
                ->assertSet('simulatorNode', $node);

            $this->assertSame($scope, $simulator->instance()->simulatorContext['scope']);
            $this->assertSame($service, $simulator->instance()->simulatorContext['service']);
        }
    }

    public function test_map_highlights_the_branch_and_step_of_the_active_conversation(): void
    {
        $simulator = Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Quisiera cotizar el envio de un paquete')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_scope')
            ->assertSee('quote-flow is-current-flow', false)
            ->assertDontSee('tracking-flow is-current-flow', false)
            ->assertSee('map-node decision-node is-current', false);

        $simulator
            ->call('resetSimulator')
            ->set('simulatorInput', 'Consultar un envío internacional')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_number')
            ->assertSee('tracking-flow is-current-flow', false)
            ->assertDontSee('quote-flow is-current-flow', false)
            ->assertSee('map-node decision-node is-current', false);
    }

    public function test_simulator_keeps_cities_from_a_natural_send_request(): void
    {
        $message = 'Quiero mandar un pauqete de la paz a oruro';
        $simulator = Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', $message)
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_scope')
            ->set('simulatorInput', 'Me podrias explicar en que consisten?')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_scope')
            ->assertSee('hasta 5 kg')
            ->assertSee('EMS INT')
            ->set('simulatorInput', 'Nacional')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_weight')
            ->assertSee('¿Cuánto pesa el paquete?');

        $codes = app(\App\Services\PostarQuoteService::class)->destinationCodesFromText($message);
        $this->assertSame($codes[0], $simulator->instance()->simulatorContext['origin']);
        $this->assertSame($codes[array_key_last($codes)], $simulator->instance()->simulatorContext['destination']);
    }

    public function test_simulator_keeps_asking_after_natural_route_and_mode_questions(): void
    {
        config(['services.delivery_express.url' => 'https://rates.test/delivery']);
        Http::fake(['rates.test/*' => Http::response([[
            'peso1' => '18.50',
            'min_weight_kg' => 0,
            'max_weight_kg' => 5,
            'origen' => ['nombre_origen' => 'LA PAZ'],
            'destino' => ['nombre_destino' => 'ORURO'],
            'servicio_extra' => ['nombre' => 'SERVICIO VENTANILLA A VENTANILLA'],
        ]], 200)]);

        $message = 'Quisiera enviar un pauquete';
        $simulator = Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', $message)
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_scope')
            ->set('simulatorInput', 'Me podira explicar en que cosiste sus envios?')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_scope')
            ->set('simulatorInput', 'Quisiera mandar un pauquete de La Paz a Oruro')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_service')
            ->set('simulatorInput', 'Delivery Express por favor')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_weight')
            ->set('simulatorInput', '4 kilos')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_mode')
            ->set('simulatorInput', 'En que varia?')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_mode')
            ->assertSee('Puerta a puerta')
            ->assertSee('Ventanilla a ventanilla')
            ->set('simulatorInput', 'Ventanilla a ventanilla')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_terminal')
            ->assertSee('Tarifa oficial verificada de Delivery Express: Bs 18.50')
            ->assertSee('desde La Paz hasta Oruro')
            ->assertSee('modalidad ventanilla a ventanilla');

        Http::assertSent(fn ($request) => $request->url() === 'https://rates.test/delivery');
    }

    public function test_simulator_queries_delivery_express_api_for_the_complete_quote(): void
    {
        config(['services.delivery_express.url' => 'https://rates.test/delivery']);
        Http::fake(['rates.test/*' => Http::response([[
            'peso1' => '21.75',
            'min_weight_kg' => 0,
            'max_weight_kg' => 5,
            'origen' => ['nombre_origen' => 'LA PAZ'],
            'destino' => ['nombre_destino' => 'TARIJA'],
            'servicio_extra' => ['nombre' => 'SERVICIO PUERTA A PUERTA'],
        ]], 200)]);

        Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Cotizar Delivery Express')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'La Paz')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'Tarija')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', '2 kilos')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'Puerta a puerta')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_terminal')
            ->assertSee('Tarifa oficial verificada de Delivery Express: Bs 21.75')
            ->assertSee('2.00 kg')
            ->assertSee('desde La Paz hasta Tarija');

        Http::assertSent(fn ($request) => $request->url() === 'https://rates.test/delivery');
    }

    public function test_simulator_does_not_show_an_estimated_price_when_delivery_api_fails(): void
    {
        config(['services.delivery_express.url' => 'https://rates.test/delivery']);
        Http::fake(['rates.test/*' => Http::response([], 503)]);

        Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Cotizar Delivery Express')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'La Paz')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'Tarija')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', '2 kilos')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'Puerta a puerta')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_terminal')
            ->assertSee('No pude verificar una tarifa')
            ->assertSee('No mostraré un precio estimado')
            ->assertDontSee('Bs 0.00');
    }

    public function test_simulator_responds_to_greeting_triggers_with_the_configured_message(): void
    {
        $greeting = AutomaticResponse::query()
            ->where('scope', 'general')
            ->where('type', 'event')
            ->where('key', 'saludo')
            ->sole();

        $this->assertContains('hola', $greeting->trigger_phrases);
        $this->assertSame('saludo', app(AutomaticResponseMatcher::class)->match('Hola')?->key);

        Livewire::test(AutomaticDecisionTrees::class)
            ->assertSet('simulatorMessages', [])
            ->set('simulatorInput', 'Hola')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'root')
            ->assertSee($greeting->template)
            ->assertDontSee('No encontré un detonante activo');
    }

    public function test_invalid_flow_keys_are_rejected(): void
    {
        $this->expectException(NotFoundHttpException::class);

        (new AutomaticDecisionTrees)->toggleDecisionFlow('unknown');
    }

    public function test_disabled_quote_tree_does_not_create_a_session(): void
    {
        DB::table('app_settings')->where('key', 'automatic_decision_flow_quote')->update(['value' => '0']);
        $response = app(AutomaticResponseMatcher::class)->match('Quiero cotizar EMS nacional');

        $this->assertNull(app(WhatsAppDecisionTreeService::class)->replyTo(
            '15551234567',
            'Quiero cotizar EMS nacional',
            $response,
        ));
        $this->assertDatabaseCount('whatsapp_decision_tree_sessions', 0);
    }

    public function test_quote_and_tracking_sessions_expire_after_inactivity(): void
    {
        $session = WhatsAppDecisionTreeSession::create([
            'phone' => '15551234567',
            'flow_key' => 'tracking',
            'step' => 'tracking_number',
            'context' => [],
            'expires_at' => now()->subMinute(),
        ]);
        $response = app(WhatsAppDecisionTreeService::class)->replyTo($session->phone, 'hola');

        $this->assertNull($response);
        $this->assertDatabaseMissing('whatsapp_decision_tree_sessions', ['id' => $session->id]);
    }
}
