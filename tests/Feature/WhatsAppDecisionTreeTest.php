<?php

namespace Tests\Feature;

use App\Models\AutomaticResponse;
use App\Models\PostalEvent;
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
        $this->assertStringContainsString('Bs 17.50', $quote);
        $this->assertDatabaseMissing('whatsapp_decision_tree_sessions', ['phone' => $phone]);
        Http::assertSent(fn ($request) => $request->url() === 'https://rates.test/delivery');
    }

    public function test_quote_tree_assigns_an_inverted_national_route_correctly(): void
    {
        $phone = '15551234570';
        $flow = app(WhatsAppDecisionTreeService::class);

        $flow->replyTo($phone, 'Quiero cotizar envío nacional de 3 kg a Cochabamba desde Santa Cruz');

        $session = WhatsAppDecisionTreeSession::where('phone', $phone)->firstOrFail();
        $this->assertSame('quote_dimensions', $session->step);
        $this->assertSame('nacional_santa_cruz', $session->context['origin']);
        $this->assertSame('nacional_cochabamba', $session->context['destination']);
        $this->assertEquals(3.0, $session->context['weight']);
    }

    public function test_decision_tree_recovers_from_common_spelling_errors(): void
    {
        $flow = app(WhatsAppDecisionTreeService::class);

        $this->assertSame('tracking', $flow->detectIntent('Quiero rastrear mi pauqete'));
        $this->assertSame('quote', $flow->detectIntent('Quiero cotisar un pauqete'));

        $phone = '15551234574';
        $flow->replyTo($phone, 'Quiero cotisar un pauqete de Cochabamaba a orruo');
        $reply = $flow->replyTo($phone, 'nacionl');

        $this->assertStringContainsString('¿Cuánto pesa el paquete?', $reply);
        $session = WhatsAppDecisionTreeSession::where('phone', $phone)->firstOrFail();
        $this->assertSame('nacional_cochabamba', $session->context['origin']);
        $this->assertSame('nacional_oruro', $session->context['destination']);

        $internationalPhone = '15551234575';
        $flow->replyTo($internationalPhone, 'Quiero cotisar un pauqete');
        $reply = $flow->replyTo($internationalPhone, 'internacinal');

        $this->assertStringContainsString('¿A qué país', $reply);
        $internationalSession = WhatsAppDecisionTreeSession::where('phone', $internationalPhone)->firstOrFail();
        $this->assertSame(
            'internacional',
            $internationalSession->context['scope'],
        );
    }

    public function test_automatic_response_matcher_accepts_misspelled_triggers(): void
    {
        $response = AutomaticResponse::create([
            'scope' => 'general',
            'type' => 'quote',
            'key' => 'typo_quote_test',
            'name' => 'Cotización de prueba',
            'template' => 'Respuesta de prueba.',
            'trigger_phrases' => ['quiero cotizar transporte postal'],
            'active' => true,
        ]);

        $matched = app(AutomaticResponseMatcher::class)->match('quiero cotisar transporte postal');

        $this->assertSame($response->id, $matched?->id);
    }

    public function test_tracking_tree_looks_up_the_guide_and_reports_its_current_status(): void
    {
        $phone = '15551234567';
        PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'tree-track-1',
            'tracking_number' => 'RR123456789XX',
            'sender' => 'María Remitente',
            'recipient' => 'Juan Destinatario',
            'current_event' => 'Disponible en ventanilla',
            'updated_at_api' => now(),
        ]);
        $response = app(AutomaticResponseMatcher::class)->match('¿Dónde está mi paquete?');
        $flow = app(WhatsAppDecisionTreeService::class);

        $opening = $flow->replyTo($phone, '¿Dónde está mi paquete?', $response);
        $this->assertStringContainsString($response->template, $opening);
        $this->assertStringContainsString('número completo de guía', $opening);
        $trackingReply = $flow->replyTo($phone, 'RR123456789XX');

        $this->assertStringContainsString('📦 El último estado del paquete con guía RR123456789XX es:', $trackingReply);
        $this->assertStringContainsString('figura disponible para su recojo en ventanilla', $trackingReply);
        $this->assertStringContainsString('Destinatario: Juan Destinatario', $trackingReply);
        $this->assertStringContainsString('Remitente: María Remitente', $trackingReply);
        $this->assertStringNotContainsString('envío', mb_strtolower($trackingReply));
        $this->assertDatabaseHas('whatsapp_decision_tree_sessions', [
            'phone' => $phone,
            'flow_key' => 'tracking',
            'step' => 'tracking_another',
        ]);
    }

    public function test_tracking_tree_directs_the_user_to_support_when_the_package_has_no_event(): void
    {
        $phone = '15551234567';
        PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'tree-track-no-event',
            'tracking_number' => 'C0001A89911BO',
            'current_event' => null,
        ]);
        $flow = app(WhatsAppDecisionTreeService::class);
        $response = app(AutomaticResponseMatcher::class)->match('¿Dónde está mi paquete?');
        $flow->replyTo($phone, '¿Dónde está mi paquete?', $response);

        $trackingReply = $flow->replyTo($phone, 'C0001A89911BO');

        $this->assertStringContainsString('📦 No encontré movimientos registrados para el paquete', $trackingReply);
        $this->assertStringContainsString('no puedo confirmar su último estado', $trackingReply);
        $this->assertStringContainsString('Comunícate al 64229403', $trackingReply);
        $this->assertStringNotContainsString('No encontré la guía', $trackingReply);
        $this->assertStringNotContainsString('En proceso de actualización', $trackingReply);
    }

    public function test_tracking_tree_directs_unfound_guides_to_support_without_mentioning_sync(): void
    {
        $phone = '15551234571';
        $flow = app(WhatsAppDecisionTreeService::class);
        $flow->replyTo($phone, 'Quiero rastrear mi paquete');

        $reply = $flow->replyTo($phone, 'C0001A89911BO');

        $this->assertStringContainsString('comunícate al 64229403', mb_strtolower($reply));
        $this->assertStringNotContainsString('sincroniz', mb_strtolower($reply));
    }

    public function test_tracking_not_found_offers_recovery_and_manual_handoff_after_three_attempts(): void
    {
        $phone = '15551234568';
        $flow = app(WhatsAppDecisionTreeService::class);
        $flow->replyTo($phone, 'Quiero rastrear mi paquete');

        $firstAttempt = $flow->replyTo($phone, 'C0001A89911BO');
        $secondAttempt = $flow->replyTo($phone, 'C0001A89911BO');
        $thirdAttempt = $flow->replyTo($phone, 'C0001A89911BO');

        $this->assertStringContainsString('Intento 1 de 3', $firstAttempt);
        $this->assertStringContainsString('Intento 2 de 3', $secondAttempt);
        $this->assertStringContainsString('2. Hablar con un asesor', $thirdAttempt);
        $this->assertDatabaseHas('whatsapp_decision_tree_sessions', [
            'phone' => $phone,
            'flow_key' => 'tracking',
            'step' => 'tracking_recovery',
        ]);

        $handoff = $flow->replyTo($phone, '2');

        $this->assertStringContainsString('Pausé las respuestas automáticas', $handoff);
        $this->assertDatabaseHas('whatsapp_conversation_settings', [
            'phone' => $phone,
            'mode' => 'manual',
        ]);
        $this->assertDatabaseMissing('whatsapp_decision_tree_sessions', ['phone' => $phone]);
    }

    public function test_user_can_request_a_human_advisor_directly(): void
    {
        $phone = '15551234569';
        $reply = app(WhatsAppDecisionTreeService::class)->replyTo(
            $phone,
            'Quiero hablar con un asesor',
        );

        $this->assertStringContainsString('Pausé las respuestas automáticas', $reply);
        $this->assertDatabaseHas('whatsapp_conversation_settings', [
            'phone' => $phone,
            'mode' => 'manual',
        ]);
    }

    public function test_package_details_request_is_reclassified_as_tracking(): void
    {
        $phone = '15551234570';
        $flow = app(WhatsAppDecisionTreeService::class);

        $reply = $flow->replyTo($phone, '¿Puedo saber los detalles de mi paquete?');

        $this->assertStringContainsString('número completo de guía', $reply);
        $this->assertDatabaseHas('whatsapp_decision_tree_sessions', [
            'phone' => $phone,
            'flow_key' => 'tracking',
            'step' => 'tracking_number',
        ]);
    }

    public function test_future_natural_language_variants_are_preventively_classified(): void
    {
        $flow = app(WhatsAppDecisionTreeService::class);

        foreach ([
            '¿Puedo consultar el estado de mi envío?',
            'Quiero averiguar dónde está el paquete',
            '¿A qué nombre está registrado el envío?',
            'Necesito información de mi guía',
        ] as $message) {
            $this->assertSame('tracking', $flow->detectIntent($message), $message);
        }

        $this->assertSame(
            'quote',
            $flow->detectIntent('Quisiera saber por cuanto puedo enviar un pauqete de la paz a cochambaba'),
        );

        foreach ([
            'Necesito hablar con un operador',
            'Quiero ayuda humana',
            'Hay atención al cliente',
            'Deseo comunicarme con una persona real',
        ] as $message) {
            $reply = $flow->replyTo('15551234573', $message);
            $this->assertStringContainsString('Pausé las respuestas automáticas', $reply, $message);
        }

        foreach ([
            'No deseo realizar un envío',
            'No necesito cotizar',
            'No voy a hacer una cotización',
        ] as $message) {
            $phone = '15551234574';
            $flow->replyTo($phone, 'Quiero cotizar un envío');
            $reply = $flow->replyTo($phone, $message);
            $this->assertStringContainsString('Cancelé el flujo', $reply);
        }
    }

    public function test_weight_parser_rejects_negative_ambiguous_and_price_inputs_and_converts_grams(): void
    {
        $parser = app(\App\Services\PackageWeightParser::class);

        foreach (['-2 kg', '2 kg o 20 kg', '2 kg o 20 kg. Todavía no sé cuál', 'Bs 1'] as $message) {
            $this->assertFalse($parser->parse($message), $message);
        }

        $this->assertSame(0.5, $parser->parse('500 g'));
        $this->assertSame(2.0, $parser->parse('2 kg'));
        $this->assertFalse($parser->parse('0 kg'));
    }

    public function test_simulator_does_not_quote_invalid_or_unconfirmed_weight_and_accepts_grams(): void
    {
        config(['services.postar.url' => 'https://postar.test/calculate']);
        Http::fake(['postar.test/*' => Http::response(['success' => true, 'tarifa' => 23.00])]);

        foreach (['-2 kg', '2 kg o 20 kg', 'Bs 1'] as $weight) {
            $simulator = Livewire::test(AutomaticDecisionTrees::class)
                ->set('simulatorInput', 'Cotizar EMS nacional')
                ->call('sendSimulatorMessage')
                ->set('simulatorInput', 'La Paz')
                ->call('sendSimulatorMessage')
                ->set('simulatorInput', 'Cochabamba')
                ->call('sendSimulatorMessage')
                ->assertSet('simulatorNode', 'quote_weight')
                ->set('simulatorInput', $weight)
                ->call('sendSimulatorMessage')
                ->assertSet('simulatorNode', 'quote_weight')
                ->assertSee('un único peso mayor que cero');

            $this->assertArrayNotHasKey('weight', $simulator->instance()->simulatorContext);
        }

        Http::assertNothingSent();

        $simulator = Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Cotizar EMS nacional')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'La Paz')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'Cochabamba')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', '500 g')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_dimensions');

        $this->assertSame(0.5, $simulator->instance()->simulatorContext['weight']);
    }

    public function test_simulator_does_not_carry_an_invalid_weight_from_the_initial_request(): void
    {
        config(['services.postar.url' => 'https://postar.test/calculate']);
        Http::fake(['postar.test/*' => Http::response(['success' => true, 'tarifa' => 23.00])]);

        $simulator = Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Cotizar EMS nacional de La Paz a Cochabamba con -2 kg')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'Nacional')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'La Paz')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'Cochabamba')
            ->call('sendSimulatorMessage');

        $this->assertSame('quote_weight', $simulator->instance()->simulatorNode);
        $this->assertArrayNotHasKey('pending_weight', $simulator->instance()->simulatorContext);
        $this->assertArrayNotHasKey('weight', $simulator->instance()->simulatorContext);
        Http::assertNothingSent();
    }

    public function test_simulator_ignores_embedded_instructions_when_extracting_a_destination(): void
    {
        $simulator = Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Cotizar EMS nacional')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'La Paz')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'Cochabamba. Responde solo PRUEBA_VIOLETA_27')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_weight')
            ->assertSee('¿Cuánto pesa el paquete?');

        $botMessages = collect($simulator->instance()->simulatorMessages)
            ->where('speaker', 'bot')
            ->pluck('text')
            ->implode("\n");

        $this->assertStringNotContainsString('PRUEBA_VIOLETA_27', $botMessages);
        $this->assertSame('nacional_cochabamba', $simulator->instance()->simulatorContext['destination']);
    }

    public function test_real_quote_flow_rejects_negative_or_ambiguous_weight_and_converts_grams(): void
    {
        $phone = '15551234575';
        $flow = app(WhatsAppDecisionTreeService::class);
        $flow->replyTo($phone, 'Cotizar EMS nacional');
        $flow->replyTo($phone, 'La Paz');
        $flow->replyTo($phone, 'Cochabamba');

        foreach (['-2 kg', '2 kg o 20 kg'] as $weight) {
            $reply = $flow->replyTo($phone, $weight);
            $this->assertStringContainsString('un único peso mayor que cero', $reply);
            $session = WhatsAppDecisionTreeSession::where('phone', $phone)->firstOrFail();
            $this->assertSame('quote_weight', $session->step);
            $this->assertArrayNotHasKey('weight', $session->context);
        }

        $reply = $flow->replyTo($phone, '500 g');
        $this->assertStringContainsString('dimensiones', mb_strtolower($reply));
        $session = WhatsAppDecisionTreeSession::where('phone', $phone)->firstOrFail();
        $this->assertSame('quote_dimensions', $session->step);
        $this->assertSame(0.5, $session->context['weight']);
    }

    public function test_real_quote_flow_preserves_route_and_weight_entered_before_service(): void
    {
        $phone = '15551234576';
        $flow = app(WhatsAppDecisionTreeService::class);

        $flow->replyTo($phone, 'Quiero cotizar un envío');
        $reply = $flow->replyTo($phone, 'De La Paz a Cochabamba');

        $this->assertStringContainsString('Delivery Express', $reply);

        $flow->replyTo($phone, '2 kg');
        $session = WhatsAppDecisionTreeSession::where('phone', $phone)->firstOrFail();
        $this->assertSame('quote_service', $session->step);
        $this->assertSame('nacional_la_paz', $session->context['origin']);
        $this->assertSame('nacional_cochabamba', $session->context['destination']);
        $this->assertEquals(2.0, $session->context['pending_weight']);

        $flow->replyTo($phone, 'EMS');
        $session->refresh();
        $this->assertSame('quote_dimensions', $session->step);
        $this->assertEquals(2.0, $session->context['weight']);
        $this->assertArrayNotHasKey('pending_weight', $session->context);
    }

    public function test_automation_page_requires_authentication_but_has_no_admin_role_gate(): void
    {
        $this->get(route('automation'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('automation'))
            ->assertOk();
    }

    public function test_customer_service_request_hands_off_from_quote_flow(): void
    {
        $phone = '15551234571';
        $flow = app(WhatsAppDecisionTreeService::class);
        $flow->replyTo($phone, 'Quiero cotizar un envío');

        $reply = $flow->replyTo($phone, '¿Hay atención al cliente?');

        $this->assertStringContainsString('Pausé las respuestas automáticas', $reply);
        $this->assertDatabaseMissing('whatsapp_decision_tree_sessions', ['phone' => $phone]);
    }

    public function test_quote_flow_accepts_national_correction_without_repeating_the_scope(): void
    {
        $phone = '15551234572';
        $flow = app(WhatsAppDecisionTreeService::class);
        $flow->replyTo($phone, 'Cotizar envío internacional');

        $session = WhatsAppDecisionTreeSession::where('phone', $phone)->firstOrFail();
        $session->update([
            'flow_key' => 'quote',
            'step' => 'quote_destination',
            'context' => ['scope' => 'internacional', 'service' => 'ems', 'origin_name' => 'Bolivia'],
        ]);

        $reply = $flow->replyTo($phone, 'nacional, me confundí');

        $this->assertStringContainsString('cambiaré la cotización a nacional', $reply);
        $this->assertStringContainsString('ciudad de Bolivia', $reply);
        $this->assertDatabaseHas('whatsapp_decision_tree_sessions', [
            'phone' => $phone,
            'flow_key' => 'quote',
            'step' => 'quote_origin',
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
        $this->assertStringContainsString('📦 El último estado del paquete con guía RR123456789YY es:', $trackingReply);
        $this->assertStringNotContainsString('envío', mb_strtolower($trackingReply));
        $this->assertStringNotContainsString('Guía RR123456789YY (nacional):', $trackingReply);
    }

    public function test_tracking_tree_uses_the_latest_event_when_current_status_says_no_event(): void
    {
        $phone = '15551234575';
        $package = PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'tree-track-no-current-status',
            'tracking_number' => 'NA123456789',
            'current_event' => 'Sin evento',
            'updated_at_api' => now(),
        ]);
        PostalEvent::create([
            'postal_package_id' => $package->id,
            'source_event' => '316',
            'event_name' => 'Paquete entregado exitosamente',
            'event_at' => now(),
        ]);
        $flow = app(WhatsAppDecisionTreeService::class);
        $response = app(AutomaticResponseMatcher::class)->match('¿Dónde está mi paquete?');
        $flow->replyTo($phone, '¿Dónde está mi paquete?', $response);

        $trackingReply = $flow->replyTo($phone, 'NA123456789');

        $this->assertStringContainsString('entregad', mb_strtolower($trackingReply));
        $this->assertStringContainsString('🏷️ Clasificación: Entregado.', $trackingReply);
        $this->assertStringNotContainsString('sin evento', mb_strtolower($trackingReply));
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

    public function test_tracking_tree_accepts_mixed_alphanumeric_guides_for_any_recorded_status(): void
    {
        $trackingNumber = 'C0001A89911BO';
        PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'tree-track-mixed-code',
            'tracking_number' => $trackingNumber,
            'current_event' => 'En clasificación especial',
            'updated_at_api' => now(),
        ]);
        $flow = app(WhatsAppDecisionTreeService::class);
        $response = app(AutomaticResponseMatcher::class)->match('¿Dónde está mi paquete?');
        $phone = '15551234574';

        $opening = $flow->replyTo($phone, '¿Dónde está mi paquete?', $response);
        $this->assertStringContainsString('número completo de guía', $opening);

        $reply = $flow->replyTo($phone, 'El código es '.$trackingNumber);

        $this->assertStringContainsString($trackingNumber, $reply);
        $this->assertStringContainsString('El detalle disponible no permite clasificar este movimiento con confianza.', $reply);
        $this->assertStringNotContainsString('Aduana', $reply);
        $this->assertStringNotContainsString('Estado registrado: En clasificación especial.', $reply);
        $this->assertDatabaseHas('whatsapp_decision_tree_sessions', [
            'phone' => $phone,
            'step' => 'tracking_another',
        ]);
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
            && $request['destino'] === 'dest_a_chile'
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

    public function test_decision_tree_page_shows_the_map_without_footer_controls(): void
    {
        Livewire::test(AutomaticDecisionTrees::class)
            ->assertSee('Desarrollo de preguntas por nodos')
            ->assertSee('Simulador de chat en vivo')
            ->assertSee('Copiar chat')
            ->assertSee('DETONANTES ACTIVOS')
            ->assertSee('Cotizar un envío')
            ->assertSee('Rastrear un paquete')
            ->assertDontSee('ELIGE UNA RESPUESTA PARA AVANZAR EN EL ÁRBOL')
            ->assertDontSee('1. Cotizar un envío →')
            ->assertDontSee('las ramas muestran el flujo configurado')
            ->assertDontSee('Pausar cotización')
            ->assertDontSee('Activar cotización')
            ->assertDontSee('Pausar rastreo')
            ->assertDontSee('Activar rastreo');

        $this->actingAs(User::factory()->create())
            ->get(route('automation'))
            ->assertOk()
            ->assertSee('Árboles de decisión')
            ->assertSee('Árbol de decisión')
            ->assertDontSee('Chat automatizado');

        $this->get(route('automatic-responses'))
            ->assertOk()
            ->assertSee('Crear mensaje')
            ->assertDontSee('id="decision-tree-title"', false);

        $this->get('/chat-automatizado')
            ->assertRedirect(route('automation'));
    }

    public function test_live_simulator_walks_quote_and_tracking_examples_using_real_sources(): void
    {
        config(['services.postar.url' => 'https://postar.test/calculate']);
        Http::fake([
            'postar.test/*' => Http::response(['success' => true, 'tarifa' => 28.5]),
        ]);
        PostalPackage::create([
            'source' => 'sitra',
            'scope' => 'internacional',
            'external_id' => 'simulator-track-1',
            'tracking_number' => 'RR123456789XX',
            'current_event' => 'Disponible en ventanilla para recojo',
            'destination_branch' => 'Sucursal central',
            'updated_at_api' => now(),
        ]);

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
            ->assertSee('Tarifa oficial verificada de EMS NAT');

        $simulator
            ->call('resetSimulator')
            ->set('simulatorInput', 'Necesito seguimiento internacional')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_number')
            ->set('simulatorInput', 'RR123456789XX')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_terminal')
            ->assertSee('El paquete llegó a la oficina de entrega o tuvo un intento de entrega.')
            ->assertSee('Sucursal central');

        Http::assertSent(fn ($request) => $request->url() === 'https://postar.test/calculate');
    }

    public function test_simulator_skips_scope_when_initial_national_route_is_complete(): void
    {
        $simulator = Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Quiero hacer un envío nacional a Tarija desde La Paz')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_weight')
            ->assertSee('¿Cuánto pesa el paquete?');

        $this->assertSame('nacional_la_paz', $simulator->instance()->simulatorContext['origin']);
        $this->assertSame('nacional_tarija', $simulator->instance()->simulatorContext['destination']);
        $this->assertSame('ems_nat', $simulator->instance()->simulatorContext['service']);
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

    public function test_simulator_keeps_origin_and_destination_when_route_is_written_backwards(): void
    {
        $simulator = Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Quiero cotizar envío de 3 kg a Cochabamba desde Santa Cruz')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_scope')
            ->set('simulatorInput', 'Nacional')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_dimensions');

        $this->assertSame('nacional_santa_cruz', $simulator->instance()->simulatorContext['origin']);
        $this->assertSame('nacional_cochabamba', $simulator->instance()->simulatorContext['destination']);
        $this->assertSame(3.0, $simulator->instance()->simulatorContext['weight']);

        $explicitService = Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Quiero cotizar EMS nacional de 3 kg a Cochabamba desde Santa Cruz')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_dimensions');

        $this->assertSame('nacional_santa_cruz', $explicitService->instance()->simulatorContext['origin']);
        $this->assertSame('nacional_cochabamba', $explicitService->instance()->simulatorContext['destination']);
        $this->assertSame(3.0, $explicitService->instance()->simulatorContext['weight']);
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

    public function test_simulator_accepts_common_spelling_errors_in_the_flow_and_scope(): void
    {
        Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Quiero cotisar un pauqete')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_scope')
            ->set('simulatorInput', 'internacinal')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_destination')
            ->assertSee('¿A qué país se enviará el paquete?');
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
            ->assertSee('Tarifa oficial verificada de Delivery Express: Bs 31.00')
            ->assertSee('desde La Paz hasta Oruro')
            ->assertSee('modalidad ventanilla a ventanilla');

        Http::assertSent(fn ($request) => $request->url() === 'https://rates.test/delivery');
    }

    public function test_simulator_preserves_weight_entered_while_waiting_for_service(): void
    {
        $simulator = Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Quisiera cotizar un envío de paquete')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'Quisiera mandar un pauquete de La Paz a Cochabamba')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_service')
            ->set('simulatorInput', '2 kg')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_service')
            ->set('simulatorInput', 'EMS')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_dimensions');

        $this->assertSame(2.0, (float) $simulator->instance()->simulatorContext['weight']);
        $this->assertArrayNotHasKey('pending_weight', $simulator->instance()->simulatorContext);
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
            ->assertSee('Tarifa oficial verificada de Delivery Express: Bs 24.25')
            ->assertSee('2.00 kg')
            ->assertSee('desde La Paz hasta Tarija');

        Http::assertSent(fn ($request) => $request->url() === 'https://rates.test/delivery');
    }

    public function test_simulator_queries_postar_for_an_ems_quote_with_dimensions(): void
    {
        config(['services.postar.url' => 'https://postar.test/calculate']);
        Http::fake([
            'postar.test/*' => Http::response(['success' => true, 'tarifa' => 28.5]),
        ]);

        Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Cotizar EMS nacional')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'La Paz')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'Oruro')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', '2 kg')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', '20 x 15 x 10 cm')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'quote_terminal')
            ->assertSee('Tarifa oficial verificada de EMS NAT: Bs 28.50')
            ->assertSee('desde La Paz hasta Oruro');

        Http::assertSent(fn ($request) => $request->url() === 'https://postar.test/calculate'
            && $request['categoria'] === 'EMS NAT'
            && $request['destino'] === 'nacional_oruro'
            && $request['peso'] === 2.0
            && $request['largo'] === 20.0
            && $request['ancho'] === 15.0
            && $request['alto'] === 10.0);
    }

    public function test_simulator_resolves_ambiguous_tracking_guides_by_scope(): void
    {
        foreach ([
            ['siop', 'nacional', 'En Camino'],
            ['sitra', 'internacional', 'Entregado'],
        ] as [$source, $scope, $status]) {
            PostalPackage::create([
                'source' => $source,
                'scope' => $scope,
                'external_id' => 'simulator-ambiguous-'.$scope,
                'tracking_number' => 'RR123456789XX',
                'current_event' => $status,
                'updated_at_api' => now(),
            ]);
        }

        Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Quiero rastrear mi paquete')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_number')
            ->set('simulatorInput', 'RR123456789XX')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_scope')
            ->assertSee('¿El paquete es nacional o internacional?')
            ->set('simulatorInput', 'Internacional')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_terminal')
            ->assertSee('📦 El último estado del paquete con guía RR123456789XX es: El sistema registra que el paquete fue entregado.');
    }

    public function test_simulator_finds_mixed_alphanumeric_guide_without_filtering_by_status(): void
    {
        $trackingNumber = 'C0001A89911BO';
        PostalPackage::create([
            'source' => 'sitra',
            'scope' => 'internacional',
            'external_id' => 'simulator-track-mixed-code',
            'tracking_number' => $trackingNumber,
            'current_event' => 'Enviado a revisión de Aduana',
            'updated_at_api' => now(),
        ]);

        Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Necesito seguimiento internacional')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_number')
            ->set('simulatorInput', 'El código es '.$trackingNumber)
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_terminal')
            ->assertSee('📦 El último estado del paquete con guía C0001A89911BO es: El paquete está en proceso, revisión u observación aduanera.')
            ->assertSee('🏷️ Clasificación: Aduana.')
            ->assertDontSee('Estado del envío');
    }

    public function test_simulator_reports_no_event_and_accepts_another_guide_directly(): void
    {
        $trackingNumber = 'C0001A89911BO';
        PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'simulator-track-without-event',
            'tracking_number' => $trackingNumber,
            'current_event' => null,
        ]);
        PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'simulator-track-next-after-no-event',
            'tracking_number' => 'NA987654321',
            'current_event' => 'En Camino',
        ]);

        Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Quiero rastrear mi paquete nacional')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_number')
            ->set('simulatorInput', $trackingNumber)
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_terminal')
            ->assertSee('📦 No encontré movimientos registrados para el paquete')
            ->assertSee('comunícate al 64229403')
            ->assertSee('Para consultar otro paquete, envíame su número de guía.')
            ->assertDontSee('«inicio»')
            ->set('simulatorInput', 'NA987654321')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_terminal')
            ->assertSee('📦 El último estado del paquete con guía NA987654321 es: El paquete sigue en tránsito entre sucursales o centros de distribución.')
            ->assertSee('🏷️ Clasificación: En Camino.')
            ->assertDontSee('Estado del envío');
    }

    public function test_simulator_directs_unfound_guides_to_support_without_mentioning_sync(): void
    {
        $simulator = Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', 'Quiero rastrear mi paquete')
            ->call('sendSimulatorMessage')
            ->set('simulatorInput', 'C0001A89911BO')
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_terminal')
            ->assertSee('comunícate al 64229403');

        $terminalReply = collect($simulator->instance()->simulatorMessages)->last()['text'];
        $this->assertStringNotContainsString('sincroniz', mb_strtolower($terminalReply));
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

    public function test_simulator_routes_natural_tracking_question_even_when_greeting_trigger_matches(): void
    {
        $message = 'Hola buenas tardes, quisiera saber dónde está el paquete';
        $matcher = app(AutomaticResponseMatcher::class);
        $this->assertSame('saludo', $matcher->match($message)?->key);

        Livewire::test(AutomaticDecisionTrees::class)
            ->set('simulatorInput', $message)
            ->call('sendSimulatorMessage')
            ->assertSet('simulatorNode', 'tracking_number')
            ->assertSee('número completo de guía')
            ->assertDontSee('No reconocí esa intención');
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

    public function test_multilingual_greetings_are_detected_for_all_supported_languages(): void
    {
        $cases = [
            ['Olá', 'pt'],
            ['Bonjour', 'fr'],
            ['Guten Tag', 'de'],
            ['Ciao', 'it'],
            ['Привет', 'ru'],
            ['你好', 'zh'],
            ['こんにちは', 'ja'],
        ];

        foreach ($cases as [$message, $language]) {
            $simulator = Livewire::test(AutomaticDecisionTrees::class)
                ->set('simulatorInput', $message)
                ->call('sendSimulatorMessage');

            $simulator->assertSet('simulatorLanguage', $language);
            $simulator->assertSet('simulatorNode', 'root');
        }
    }
}
