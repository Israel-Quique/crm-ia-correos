<?php

namespace Tests\Feature;

use App\Livewire\AutomaticResponses;
use App\Models\AutomaticResponse;
use App\Services\AutomaticResponseMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AutomaticResponsesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_a_custom_response_and_splits_trigger_phrases(): void
    {
        Livewire::test(AutomaticResponses::class)
            ->set('scope', 'nacional')
            ->set('type', 'event')
            ->set('key', 'custom_status')
            ->set('name', 'Estado personalizado')
            ->set('triggerPhrases', "paquete demorado, dónde está\nseguimiento pendiente")
            ->set('template', 'Estamos revisando tu envío.')
            ->set('active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('automatic-response-saved');

        $response = AutomaticResponse::query()
            ->where('name', 'Estado personalizado')
            ->sole();

        $this->assertSame('nacional', $response->scope);
        $this->assertSame('event', $response->type);
        $this->assertSame('custom_status', $response->key);
        $this->assertSame(
            ['paquete demorado', 'dónde está', 'seguimiento pendiente'],
            $response->trigger_phrases,
        );
        $this->assertSame('Estamos revisando tu envío.', $response->template);
        $this->assertTrue($response->active);
    }

    public function test_it_shows_validation_errors_and_does_not_save_an_incomplete_response(): void
    {
        Livewire::test(AutomaticResponses::class)
            ->call('save')
            ->assertHasErrors(['key', 'name', 'template']);

        $this->assertDatabaseMissing('automatic_responses', [
            'name' => '',
            'template' => '',
        ]);
    }

    public function test_it_edits_an_existing_response_without_creating_a_duplicate(): void
    {
        $response = AutomaticResponse::create([
            'scope' => 'nacional',
            'type' => 'event',
            'key' => 'en_camino',
            'name' => 'En camino',
            'template' => 'Tu paquete va en camino.',
            'trigger_phrases' => ['va en camino'],
            'active' => true,
        ]);
        $responseCount = AutomaticResponse::count();

        Livewire::test(AutomaticResponses::class)
            ->call('edit', $response->id)
            ->assertSet('editingResponseId', $response->id)
            ->assertSet('scope', 'nacional')
            ->assertSet('triggerPhrases', 'va en camino')
            ->set('name', 'En tránsito')
            ->set('triggerPhrases', "en tránsito, camino al destino\npaquete enviado")
            ->set('template', 'El paquete ya salió de la sucursal.')
            ->set('active', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('automatic-response-saved')
            ->assertSet('editingResponseId', null);

        $this->assertSame($responseCount, AutomaticResponse::count());
        $response->refresh();
        $this->assertSame('En tránsito', $response->name);
        $this->assertSame(['en tránsito', 'camino al destino', 'paquete enviado'], $response->trigger_phrases);
        $this->assertSame('El paquete ya salió de la sucursal.', $response->template);
        $this->assertFalse($response->active);
    }

    public function test_the_modal_offers_known_keys_and_allows_a_custom_key(): void
    {
        $component = Livewire::test(AutomaticResponses::class)
            ->set('scope', 'nacional')
            ->set('type', 'quote')
            ->set('key', 'tarifa especial')
            ->assertSet('key', 'tarifa especial');

        $this->assertContains('recibido_origen', $component->instance()->keySuggestions());
        $this->assertContains('saludo', $component->instance()->keySuggestions());
        $this->assertContains('EMS NAT', $component->instance()->keySuggestions());
    }

    public function test_default_delivery_and_tracking_answers_are_available_and_match_their_questions(): void
    {
        $deliveryResponse = AutomaticResponse::query()
            ->where('key', 'Delivery Express')
            ->sole();
        $nationalResponse = AutomaticResponse::query()
            ->where('key', 'rastreo_nacional')
            ->sole();
        $internationalResponse = AutomaticResponse::query()
            ->where('key', 'rastreo_internacional')
            ->sole();
        $generalQuoteResponse = AutomaticResponse::query()
            ->where('key', 'cotizacion_postal')
            ->sole();
        $generalTrackingResponse = AutomaticResponse::query()
            ->where('key', 'rastreo_postal')
            ->sole();

        $this->assertSame('quote', $deliveryResponse->type);
        $this->assertContains('cotizar delivery express', $deliveryResponse->trigger_phrases);
        $this->assertStringContainsString('5 kg', $deliveryResponse->template);
        $this->assertStringStartsWith('🚚', $deliveryResponse->template);
        $this->assertSame('tracking', $nationalResponse->type);
        $this->assertSame('tracking', $internationalResponse->type);
        $this->assertSame('quote', $generalQuoteResponse->type);
        $this->assertSame('tracking', $generalTrackingResponse->type);
        $this->assertStringStartsWith('🔎', $nationalResponse->template);
        $this->assertStringStartsWith('🌎', $internationalResponse->template);

        foreach (['EMS NAT', 'EMS INT'] as $quoteKey) {
            $quote = AutomaticResponse::query()->where('key', $quoteKey)->sole();
            $this->assertMatchesRegularExpression('/^\p{So}/u', $quote->template);
        }

        foreach (AutomaticResponse::query()->whereNull('user_id')->get() as $defaultResponse) {
            $this->assertMatchesRegularExpression(
                '/^\p{So}/u',
                $defaultResponse->template,
                "La respuesta automática {$defaultResponse->key} debe iniciar con un emoji.",
            );
        }

        $matcher = app(AutomaticResponseMatcher::class);
        $this->assertSame('Delivery Express', $matcher->match('Quiero cotizar Delivery Express')?->key);
        $this->assertSame('EMS NAT', $matcher->match('Cotizar EMS nacional')?->key);
        $this->assertSame('EMS INT', $matcher->match('Cotizar EMS internacional')?->key);
        $this->assertSame('rastreo_postal', $matcher->match('Donde esta mi paquete?')?->key);
        $this->assertSame('rastreo_nacional', $matcher->match('Quiero consultar un envio nacional')?->key);
        $this->assertSame('rastreo_internacional', $matcher->match('Necesito seguimiento internacional')?->key);
        $this->assertSame('cotizacion_postal', $matcher->match('cotizar paquete')?->key);
        $this->assertSame('saludo', $matcher->match('Buenas tardes')?->key);
        $this->assertNull($matcher->match('La guía aparece como entregada.'));

        Livewire::test(AutomaticResponses::class)
            ->set('typeFilter', 'tracking')
            ->assertSee('Consulta de rastreo nacional')
            ->assertSee('Consulta de rastreo internacional')
            ->assertSee('Rastreo');
    }

    public function test_a_tracking_answer_can_be_created_from_the_editor(): void
    {
        Livewire::test(AutomaticResponses::class)
            ->set('scope', 'internacional')
            ->set('type', 'tracking')
            ->set('key', 'consulta_sitra')
            ->set('name', 'Consulta de guía SITRA')
            ->set('triggerPhrases', 'rastrear paquete internacional')
            ->set('template', 'Compárteme el número completo de guía.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('automatic_responses', [
            'scope' => 'internacional',
            'type' => 'tracking',
            'key' => 'consulta_sitra',
            'name' => 'Consulta de guía SITRA',
        ]);
    }

    public function test_response_pagination_uses_numbered_controls_and_resets_after_filtering(): void
    {
        foreach (range(1, 17) as $index) {
            AutomaticResponse::create([
                'scope' => 'general',
                'type' => 'tracking',
                'key' => 'status_'.$index,
                'name' => 'Respuesta '.$index,
                'template' => 'Mensaje '.$index,
                'active' => true,
            ]);
        }

        $trackingResponsesCount = AutomaticResponse::query()
            ->where('scope', 'general')
            ->where('type', 'tracking')
            ->count();

        Livewire::test(AutomaticResponses::class)
            ->set('scopeFilter', 'general')
            ->set('typeFilter', 'tracking')
            ->assertSee('Mostrando 1–8 de '.number_format($trackingResponsesCount).' respuestas')
            ->assertSee('aria-label="Ir a página 2"', false)
            ->assertSee('aria-current="page"', false)
            ->call('gotoPage', 2)
            ->assertSee('Mostrando 9–16 de '.number_format($trackingResponsesCount).' respuestas')
            ->assertSee('Respuesta 9')
            ->assertDontSee('Respuesta 1')
            ->set('keyFilter', 'status_1')
            ->assertSet('paginators.page', 1)
            ->assertSee('Respuesta 1')
            ->assertDontSee('Respuesta 9')
            ->call('clearFilters')
            ->assertSet('scopeFilter', 'all')
            ->assertSet('typeFilter', 'all')
            ->assertSet('keyFilter', 'all')
            ->assertSet('statusFilter', 'all')
            ->assertSee('Mostrando 1–8 de '.number_format(AutomaticResponse::count()).' respuestas');
    }

    public function test_response_filters_cascade_keys_and_filter_by_status(): void
    {
        AutomaticResponse::create([
            'scope' => 'nacional',
            'type' => 'event',
            'key' => 'en_camino',
            'name' => 'En camino',
            'template' => 'El paquete está en camino.',
            'active' => true,
        ]);
        AutomaticResponse::create([
            'scope' => 'internacional',
            'type' => 'tracking',
            'key' => 'custom_rastreo_inactivo',
            'name' => 'Respuesta SITRA inactiva',
            'template' => 'Envíame la guía.',
            'active' => false,
        ]);

        Livewire::test(AutomaticResponses::class)
            ->assertSee('<option value="en_camino">en_camino</option>', false)
            ->assertSee('<option value="rastreo_internacional">rastreo_internacional</option>', false)
            ->set('keyFilter', 'en_camino')
            ->set('scopeFilter', 'nacional')
            ->assertSee('En camino')
            ->set('typeFilter', 'tracking')
            ->assertSet('keyFilter', 'all')
            ->assertSee('Consulta de rastreo nacional')
            ->assertDontSee('En camino')
            ->set('scopeFilter', 'internacional')
            ->assertSee('Consulta de rastreo internacional')
            ->set('statusFilter', 'active')
            ->assertSee('Consulta de rastreo internacional')
            ->assertDontSee('Respuesta SITRA inactiva')
            ->set('statusFilter', 'inactive')
            ->assertSee('Respuesta SITRA inactiva')
            ->assertDontSee('Consulta de rastreo internacional');
    }
}
