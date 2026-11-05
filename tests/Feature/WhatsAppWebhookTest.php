<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AutomaticResponse;
use App\Models\PostalCampaign;
use App\Models\PostalCampaignMessage;
use App\Models\PostalPackage;
use App\Models\WhatsAppMessage;
use App\Livewire\Chat;
use App\Services\WhatsAppAutoReplyService;
use Carbon\Carbon;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_PHONE = '15551234567';
    private const APP_SECRET = 'webhook-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.app_secret' => self::APP_SECRET,
            'services.whatsapp.test_recipient' => self::TEST_PHONE,
            'services.whatsapp.phone_number_id' => 'test-phone-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.api_version' => 'v23.0',
        ]);
    }

    public function test_it_saves_and_replies_to_a_test_whatsapp_message_once(): void
    {
        $this->setPreference('test_mode', true);
        $this->setPreference('ai_enabled', true);

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('chat')->once()->with('Hola', [], 'general')->andReturn('¡Hola! ¿En qué te ayudo?');
        $this->app->instance(OllamaService::class, $ollama);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out.1']]], 200),
        ]);

        $payload = $this->incomingPayload(self::TEST_PHONE, 'wamid.in.1', 'Hola');
        $this->postWebhook($payload)->assertOk()->assertJson(['received' => true]);
        $this->postWebhook($payload)->assertOk()->assertJson(['received' => true]);
        $this->processAutoReply('wamid.in.1');

        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.in.1',
            'phone' => self::TEST_PHONE,
            'direction' => 'inbound',
            'contact_name' => 'Contacto de prueba',
            'auto_reply_status' => 'sent',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.out.1',
            'phone' => self::TEST_PHONE,
            'direction' => 'outbound',
            'message' => '¡Hola! ¿En qué te ayudo?',
        ]);
        $this->assertSame(2, WhatsAppMessage::count());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['to'] === self::TEST_PHONE
            && $request['text']['body'] === '¡Hola! ¿En qué te ayudo?');
    }

    public function test_agent_replies_to_any_registered_test_number(): void
    {
        $this->setPreference('test_mode', true);
        $this->setPreference('ai_enabled', true);
        $sender = '15557654321';
        DB::table('app_settings')->insert([
            'key' => 'whatsapp_test_numbers',
            'value' => json_encode([$sender]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('chat')->once()->with('Hola', [], 'general')->andReturn('Respuesta de prueba.');
        $this->app->instance(OllamaService::class, $ollama);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out.registered']]], 200),
        ]);

        $this->postWebhook($this->incomingPayload($sender, 'wamid.in.registered', 'Hola'))
            ->assertOk()
            ->assertJson(['received' => true]);
        $this->processAutoReply('wamid.in.registered');

        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.out.registered',
            'phone' => $sender,
            'direction' => 'outbound',
            'message' => 'Respuesta de prueba.',
        ]);
        Http::assertSent(fn ($request) => $request['to'] === $sender);
    }

    public function test_chat_displays_message_timestamps_in_bolivia_time(): void
    {
        config(['app.timezone' => 'UTC']);
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.timezone',
            'phone' => self::TEST_PHONE,
            'direction' => 'outbound',
            'message_type' => 'text',
            'message' => 'hola',
            'status' => 'read',
            'received_at' => Carbon::parse('2026-11-03 19:32:00', 'UTC'),
        ]);

        Livewire::test(Chat::class)
            ->assertSee('hola')
            ->assertSee('03/11/2026 15:32')
            ->assertDontSee('03/11/2026 19:32')
            ->assertSee('Visto')
            ->assertSee('✓✓');
    }

    public function test_webhook_updates_the_delivery_status_of_a_campaign_message(): void
    {
        $user = User::factory()->create();
        $package = PostalPackage::create([
            'source' => 'siop',
            'scope' => 'nacional',
            'external_id' => 'webhook-status-package',
            'tracking_number' => 'NA987654321',
            'phone' => self::TEST_PHONE,
        ]);
        $campaign = PostalCampaign::create([
            'user_id' => $user->id,
            'name' => 'Campaña de estado',
            'filters' => ['scope' => 'nacional'],
            'template' => 'Estado de envío',
            'status' => 'sent',
            'total' => 1,
            'sent' => 1,
        ]);
        $campaignMessage = PostalCampaignMessage::create([
            'postal_campaign_id' => $campaign->id,
            'postal_package_id' => $package->id,
            'phone' => self::TEST_PHONE,
            'message' => 'Tu paquete está en camino.',
            'status' => 'sent',
            'provider_message_id' => 'wamid.campaign.status',
            'sent_at' => now()->subMinute(),
        ]);

        $this->postWebhook([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.campaign.status',
                            'status' => 'delivered',
                            'timestamp' => (string) now()->timestamp,
                            'recipient_id' => self::TEST_PHONE,
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('postal_campaign_messages', [
            'id' => $campaignMessage->id,
            'status' => 'delivered',
        ]);
    }

    public function test_it_does_not_reply_when_disabled_or_to_a_non_test_number(): void
    {
        $this->setPreference('test_mode', true);
        $this->setPreference('ai_enabled', false);

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldNotReceive('chat');
        $this->app->instance(OllamaService::class, $ollama);
        Http::fake();

        $this->postWebhook($this->incomingPayload(self::TEST_PHONE, 'wamid.in.disabled', 'Hola'))->assertOk();

        $this->setPreference('ai_enabled', true);
        $this->postWebhook($this->incomingPayload('15557654321', 'wamid.in.other', 'Hola'))->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.in.disabled',
            'direction' => 'inbound',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.in.other',
            'direction' => 'inbound',
        ]);
        $this->assertSame(2, WhatsAppMessage::count());
        Http::assertNothingSent();
    }

    public function test_agent_replies_to_the_sender_when_test_mode_is_disabled(): void
    {
        $this->setPreference('test_mode', false);
        $this->setPreference('ai_enabled', true);

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('chat')->once()->with('Hola', [], 'general')->andReturn('Respuesta del agente.');
        $this->app->instance(OllamaService::class, $ollama);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out.live']]], 200),
        ]);

        $sender = '15557654321';
        $this->postWebhook($this->incomingPayload($sender, 'wamid.in.live', 'Hola'))
            ->assertOk()
            ->assertJson(['received' => true]);
        $this->processAutoReply('wamid.in.live');

        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.out.live',
            'phone' => $sender,
            'direction' => 'outbound',
            'message' => 'Respuesta del agente.',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.in.live',
            'auto_reply_status' => 'sent',
        ]);
        Http::assertSent(fn ($request) => $request['to'] === $sender
            && $request['text']['body'] === 'Respuesta del agente.');
    }

    public function test_manual_chat_message_to_registered_test_number_is_sent_to_its_original_number(): void
    {
        $this->setPreference('test_mode', true);
        DB::table('app_settings')->insert([
            'key' => 'whatsapp_test_numbers',
            'value' => json_encode([['name' => 'Cristian', 'number' => '15557654321']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.chat.registered.in',
            'phone' => '15557654321',
            'contact_name' => 'Cristian',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Hola',
            'status' => 'received',
            'received_at' => now(),
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.chat.registered.out']]], 200),
        ]);

        Livewire::test(Chat::class)
            ->set('message', 'Respuesta para Cristian')
            ->call('sendMessage')
            ->assertHasNoErrors();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['to'] === '15557654321');
        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.chat.registered.out',
            'phone' => '15557654321',
            'contact_name' => 'Cristian',
            'direction' => 'outbound',
            'message' => 'Respuesta para Cristian',
        ]);
    }

    public function test_manual_chat_message_to_unregistered_number_is_redirected_in_test_mode(): void
    {
        $this->setPreference('test_mode', true);
        $sender = '15557654321';
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.chat.unregistered.in',
            'phone' => $sender,
            'contact_name' => 'Contacto no registrado',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Hola',
            'status' => 'received',
            'received_at' => now(),
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.chat.redirected']]], 200),
        ]);

        Livewire::test(Chat::class)
            ->set('message', 'Mensaje de prueba')
            ->call('sendMessage')
            ->assertHasNoErrors();

        Http::assertSent(fn ($request) => $request['to'] === self::TEST_PHONE);
        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.chat.redirected',
            'phone' => self::TEST_PHONE,
            'direction' => 'outbound',
            'message' => 'Mensaje de prueba',
        ]);
    }

    public function test_failed_agent_reply_is_recorded_without_failing_the_meta_webhook(): void
    {
        $this->setPreference('test_mode', false);
        $this->setPreference('ai_enabled', true);

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('chat')->once()->andThrow(new \RuntimeException('Ollama no disponible.'));
        $this->app->instance(OllamaService::class, $ollama);
        Http::fake();

        $this->postWebhook($this->incomingPayload('15557654321', 'wamid.in.agent-failed', 'Hola'))
            ->assertOk()
            ->assertJson(['received' => true]);
        $this->processAutoReply('wamid.in.agent-failed');

        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.in.agent-failed',
            'auto_reply_status' => 'failed',
        ]);
        Http::assertNothingSent();
    }

    public function test_it_uses_a_matching_configured_automatic_response(): void
    {
        $this->setPreference('test_mode', true);
        $this->setPreference('ai_enabled', true);
        $this->setPreference('automatic_responses_enabled', true);
        AutomaticResponse::create([
            'scope' => 'general',
            'type' => 'event',
            'key' => 'saludo',
            'name' => 'Saludo',
            'template' => 'Respuesta de prueba configurada.',
            'trigger_phrases' => ['hola'],
            'active' => true,
        ]);

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldNotReceive('chat');
        $this->app->instance(OllamaService::class, $ollama);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out.canned']]], 200),
        ]);

        $this->postWebhook($this->incomingPayload(self::TEST_PHONE, 'wamid.in.canned', 'Hola'))->assertOk();
        $this->processAutoReply('wamid.in.canned');

        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.out.canned',
            'direction' => 'outbound',
            'message' => 'Respuesta de prueba configurada.',
        ]);
        Http::assertSent(fn ($request) => $request['to'] === self::TEST_PHONE);
    }

    public function test_configured_automatic_response_works_when_ai_is_disabled(): void
    {
        $this->setPreference('test_mode', true);
        $this->setPreference('ai_enabled', false);
        $this->setPreference('automatic_responses_enabled', true);
        AutomaticResponse::create([
            'scope' => 'general',
            'type' => 'event',
            'key' => 'saludo',
            'name' => 'Saludo',
            'template' => 'Respuesta automática sin IA.',
            'trigger_phrases' => ['hola'],
            'active' => true,
        ]);

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldNotReceive('chat');
        $this->app->instance(OllamaService::class, $ollama);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out.auto-only']]], 200),
        ]);

        $this->postWebhook($this->incomingPayload(self::TEST_PHONE, 'wamid.in.auto-only', 'Hola'))->assertOk();
        $this->processAutoReply('wamid.in.auto-only');

        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.out.auto-only',
            'message' => 'Respuesta automática sin IA.',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.in.auto-only',
            'auto_reply_status' => 'sent',
        ]);
    }

    public function test_ai_replies_when_configured_automatic_responses_are_disabled(): void
    {
        $this->setPreference('test_mode', true);
        $this->setPreference('ai_enabled', true);
        $this->setPreference('automatic_responses_enabled', false);
        AutomaticResponse::create([
            'scope' => 'general',
            'type' => 'event',
            'key' => 'saludo',
            'name' => 'Saludo',
            'template' => 'Esta regla está desactivada.',
            'trigger_phrases' => ['hola'],
            'active' => true,
        ]);

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('chat')->once()->with('Hola', [], 'general')->andReturn('Respuesta generada por IA.');
        $this->app->instance(OllamaService::class, $ollama);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out.ai-only']]], 200),
        ]);

        $this->postWebhook($this->incomingPayload(self::TEST_PHONE, 'wamid.in.ai-only', 'Hola'))->assertOk();
        $this->processAutoReply('wamid.in.ai-only');

        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.out.ai-only',
            'message' => 'Respuesta generada por IA.',
        ]);
        $this->assertDatabaseMissing('whatsapp_messages', [
            'message' => 'Esta regla está desactivada.',
        ]);
    }

    public function test_meta_webhook_verification_accepts_meta_query_parameters(): void
    {
        config(['services.whatsapp.verify_token' => 'verify-token']);

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-token&hub.challenge=challenge-value')
            ->assertOk()
            ->assertSeeText('challenge-value');
    }

    public function test_it_logs_message_and_status_counts_for_meta_deliveries(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Webhook de WhatsApp procesado', [
                'entries' => 1,
                'messages' => 1,
                'statuses' => 0,
            ]);

        $this->postWebhook($this->incomingPayload('15557654321', 'wamid.in.logged', 'Hola'))
            ->assertOk()
            ->assertJson(['received' => true]);
    }

    public function test_it_logs_and_rejects_an_invalid_meta_signature(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Webhook de WhatsApp rechazado: firma inválida.');

        $payload = $this->incomingPayload(self::TEST_PHONE, 'wamid.in.invalid-signature', 'Hola');
        $content = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
        ], $content)->assertForbidden();

        $this->assertDatabaseMissing('whatsapp_messages', [
            'provider_message_id' => 'wamid.in.invalid-signature',
        ]);
    }

    public function test_it_rejects_a_placeholder_app_secret_as_unconfigured(): void
    {
        config(['services.whatsapp.app_secret' => 'tu_app_secret_real_aqui']);
        Log::shouldReceive('error')
            ->once()
            ->with('Webhook de WhatsApp rechazado: WHATSAPP_APP_SECRET falta o sigue siendo un valor de ejemplo.');

        $this->postWebhook($this->incomingPayload(self::TEST_PHONE, 'wamid.in.placeholder-secret', 'Hola'))
            ->assertStatus(503)
            ->assertJson(['message' => 'Webhook de WhatsApp no configurado.']);

        $this->assertDatabaseMissing('whatsapp_messages', [
            'provider_message_id' => 'wamid.in.placeholder-secret',
        ]);
    }

    public function test_user_can_persist_agent_and_test_mode_preferences(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('settings.preferences'), [
            'test_mode' => false,
            'ai_enabled' => false,
            'automatic_responses_enabled' => true,
            'confirm_actions' => true,
        ])->assertOk()->assertJson(['saved' => true]);

        $this->assertDatabaseHas('app_settings', ['key' => 'test_mode', 'value' => '0']);
        $this->assertDatabaseHas('app_settings', ['key' => 'ai_enabled', 'value' => '0']);
        $this->assertDatabaseHas('app_settings', ['key' => 'automatic_responses_enabled', 'value' => '1']);
        $this->assertDatabaseHas('app_settings', ['key' => 'confirm_actions', 'value' => '1']);
    }

    public function test_automatic_responses_page_includes_shared_system_preferences(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPreference('test_mode', false);

        $this->get(route('automatic-responses'))
            ->assertOk()
            ->assertSee('Preferencias del sistema')
            ->assertSee('Modo prueba')
            ->assertSee('Asistente IA')
            ->assertSee('Respuestas automáticas')
            ->assertSee('Confirmar acciones sensibles')
            ->assertSee('Guardar configuración')
            ->assertSee('id="testMode" type="checkbox"', false)
            ->assertSee('id="automaticResponsesMode" type="checkbox"', false);
    }

    public function test_system_preferences_are_shared_with_the_settings_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('settings'))
            ->assertOk()
            ->assertSee('Preferencias del sistema')
            ->assertSee('Modo prueba')
            ->assertSee('Asistente IA')
            ->assertSee('Respuestas automáticas')
            ->assertSee('Guardar configuración');
    }

    public function test_chat_opens_on_the_latest_personal_conversation(): void
    {
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.chat.old',
            'phone' => '15550000001',
            'contact_name' => 'Contacto anterior',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Mensaje anterior',
            'status' => 'received',
            'received_at' => now()->subMinute(),
        ]);
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.chat.latest',
            'phone' => '15550000002',
            'contact_name' => 'Contacto reciente',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Mensaje entrante reciente',
            'status' => 'received',
            'received_at' => now(),
        ]);

        Livewire::test(\App\Livewire\Chat::class)
            ->assertSet('tab', 'personal')
            ->assertSet('selectedPhone', '15550000002')
            ->assertSee('Mensaje entrante reciente');
    }

    public function test_chat_shows_unread_incoming_count_and_clears_it_when_opened(): void
    {
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.unread.first',
            'phone' => '15550000011',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Primer mensaje',
            'status' => 'received',
            'received_at' => now()->subMinutes(3),
        ]);
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.unread.second',
            'phone' => '15550000011',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Segundo mensaje',
            'status' => 'received',
            'received_at' => now()->subMinutes(2),
        ]);
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.unread.latest',
            'phone' => '15550000012',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Chat más reciente',
            'status' => 'received',
            'received_at' => now()->subMinute(),
        ]);

        Livewire::test(Chat::class)
            ->assertSee('2 mensajes sin leer')
            ->call('selectPersonal', '15550000011')
            ->assertDontSee('2 mensajes sin leer');

        $this->assertDatabaseMissing('whatsapp_messages', [
            'provider_message_id' => 'wamid.unread.first',
            'read_at' => null,
        ]);
        $this->assertDatabaseMissing('whatsapp_messages', [
            'provider_message_id' => 'wamid.unread.second',
            'read_at' => null,
        ]);
    }

    public function test_webhook_schedules_agent_reply_at_least_one_minute_later(): void
    {
        $before = now()->timestamp;

        $this->postWebhook($this->incomingPayload(self::TEST_PHONE, 'wamid.in.delayed', 'Hola'))
            ->assertOk();

        $availableAt = DB::table('jobs')->where('queue', 'default')->value('available_at');

        $this->assertNotNull($availableAt);
        $this->assertGreaterThanOrEqual($before + 60, (int) $availableAt);
        $this->assertDatabaseMissing('whatsapp_messages', [
            'phone' => self::TEST_PHONE,
            'direction' => 'outbound',
        ]);
    }

    public function test_agent_combines_messages_received_within_the_one_minute_wait(): void
    {
        $this->setPreference('test_mode', false);
        $this->setPreference('ai_enabled', true);
        $firstAt = now()->subMinute();

        $first = WhatsAppMessage::create([
            'provider_message_id' => 'wamid.batch.first',
            'phone' => '15557654321',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Hola',
            'status' => 'received',
            'received_at' => $firstAt,
        ]);
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.batch.second',
            'phone' => '15557654321',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Quiero saber dónde está mi paquete',
            'status' => 'received',
            'received_at' => $firstAt->copy()->addSeconds(30),
        ]);

        $ollama = Mockery::mock(OllamaService::class);
        $ollama->shouldReceive('chat')
            ->once()
            ->with("Hola\nQuiero saber dónde está mi paquete", [], 'general')
            ->andReturn('Tu paquete está en camino.');
        $this->app->instance(OllamaService::class, $ollama);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.batch.reply']]], 200),
        ]);

        app(WhatsAppAutoReplyService::class)->replyTo($first);

        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.batch.first',
            'auto_reply_status' => 'sent',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.batch.second',
            'auto_reply_status' => 'sent',
        ]);
        Http::assertSentCount(1);
    }

    public function test_chat_header_opens_and_closes_the_contact_profile_sidebar(): void
    {
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.profile.in',
            'phone' => '15550000003',
            'contact_name' => 'Contacto perfil',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Hola',
            'status' => 'received',
            'received_at' => now()->subMinute(),
        ]);
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.profile.out',
            'phone' => '15550000003',
            'contact_name' => 'Contacto perfil',
            'direction' => 'outbound',
            'message_type' => 'text',
            'message' => 'Respuesta',
            'status' => 'sent',
            'received_at' => now(),
        ]);

        Livewire::test(\App\Livewire\Chat::class)
            ->assertSet('showContactProfile', false)
            ->call('toggleContactProfile')
            ->assertSet('showContactProfile', true)
            ->assertSee('Info. del contacto')
            ->assertSee('Contacto perfil')
            ->assertSee('15550000003')
            ->assertSee('Mensajes')
            ->assertSee('Recibidos')
            ->assertSee('Enviados')
            ->call('closeContactProfile')
            ->assertSet('showContactProfile', false)
            ->assertDontSee('Info. del contacto');
    }

    public function test_contact_profile_shows_multimedia_and_links_from_the_chat(): void
    {
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.profile.image',
            'phone' => '15550000004',
            'contact_name' => 'Contacto multimedia',
            'direction' => 'inbound',
            'message_type' => 'image',
            'message' => '[Image]',
            'status' => 'received',
            'raw_payload' => ['image' => ['caption' => 'Foto del paquete']],
            'received_at' => now(),
        ]);
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.profile.link',
            'phone' => '15550000004',
            'contact_name' => 'Contacto multimedia',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Mira https://example.com/paquete.',
            'status' => 'received',
            'received_at' => now(),
        ]);

        Livewire::test(\App\Livewire\Chat::class)
            ->call('toggleContactProfile')
            ->assertSee('Multimedia, enlaces y documentos')
            ->assertSee('Foto del paquete')
            ->assertSee('https://example.com/paquete');
    }

    public function test_received_image_is_loaded_in_message_and_contact_media_panel(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('local');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7S8AAAAASUVORK5CYII=', true);
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'url' => 'https://lookaside.fbsbx.com/whatsapp/test-image',
                'mime_type' => 'image/png',
            ]),
            'lookaside.fbsbx.com/*' => Http::response($image, 200, ['Content-Type' => 'image/png']),
        ]);
        $payload = $this->incomingPayload(self::TEST_PHONE, 'wamid.image.loaded', '');
        $payload['entry'][0]['changes'][0]['value']['messages'][0] = [
            'from' => self::TEST_PHONE,
            'id' => 'wamid.image.loaded',
            'timestamp' => (string) now()->timestamp,
            'type' => 'image',
            'image' => ['id' => 'media.image.1', 'mime_type' => 'image/png', 'caption' => 'Foto del paquete'],
        ];

        $this->postWebhook($payload)->assertOk()->assertJson(['received' => true]);
        $message = WhatsAppMessage::where('provider_message_id', 'wamid.image.loaded')->firstOrFail();
        $path = data_get($message->raw_payload, 'image.local_path');
        Storage::disk('local')->assertExists($path);

        Livewire::test(\App\Livewire\Chat::class)
            ->assertSee(route('whatsapp.media', $message), false)
            ->assertSee('aria-label="Ampliar imagen"', false)
            ->assertSee('Vista ampliada de imagen')
            ->assertSee('Foto del paquete')
            ->call('toggleContactProfile')
            ->assertSee('Imagen')
            ->assertSee(route('whatsapp.media', $message), false);

        $this->get(route('whatsapp.media', $message))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertContent($image);
    }

    public function test_clearing_chat_deletes_only_the_selected_contact_history(): void
    {
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.clear.selected',
            'phone' => '15550000005',
            'contact_name' => 'Contacto a limpiar',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Borrar este mensaje',
            'status' => 'received',
            'received_at' => now(),
        ]);
        WhatsAppMessage::create([
            'provider_message_id' => 'wamid.clear.other',
            'phone' => '15550000006',
            'contact_name' => 'Otro contacto',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Conservar este mensaje',
            'status' => 'received',
            'received_at' => now()->subMinute(),
        ]);

        Livewire::test(\App\Livewire\Chat::class)
            ->assertSet('selectedPhone', '15550000005')
            ->call('toggleContactProfile')
            ->assertSee('Limpiar chat')
            ->call('clearChat')
            ->assertSet('selectedPhone', null)
            ->assertSet('showContactProfile', false)
            ->assertSee('Se limpió el historial de esta conversación.');

        $this->assertDatabaseMissing('whatsapp_messages', ['provider_message_id' => 'wamid.clear.selected']);
        $this->assertDatabaseHas('whatsapp_messages', ['provider_message_id' => 'wamid.clear.other']);
    }

    private function setPreference(string $key, bool $enabled): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $enabled ? '1' : '0', 'updated_at' => now()],
        );
    }

    private function processAutoReply(string $providerMessageId): void
    {
        $message = WhatsAppMessage::where('provider_message_id', $providerMessageId)->firstOrFail();
        app(WhatsAppAutoReplyService::class)->replyTo($message);
    }

    private function incomingPayload(string $phone, string $messageId, string $text): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'contacts' => [[
                            'wa_id' => $phone,
                            'profile' => ['name' => 'Contacto de prueba'],
                        ]],
                        'messages' => [[
                            'from' => $phone,
                            'id' => $messageId,
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => $text],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    private function postWebhook(array $payload)
    {
        $content = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $content, self::APP_SECRET),
        ], $content);
    }
}
