<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\PostalCampaign;
use App\Models\PostalPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_only_outbound_messages_created_in_the_current_month(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(15, 32));
        Http::fake();
        $this->actingAs(User::factory()->create());

        foreach ([
            ['outbound', 'sent', now()->startOfMonth()],
            ['outbound', 'delivered', now()->startOfMonth()->addDays(4)],
            ['outbound', 'replied', now()],
            ['inbound', 'received', now()],
            ['outbound', 'failed', now()],
            ['outbound', 'pending', now()],
            ['outbound', 'sent', now()->subMonth()],
            ['outbound', 'sent', now()->startOfMonth()->addMonth()],
        ] as $index => [$direction, $status, $createdAt]) {
            $message = WhatsAppMessage::create([
                'phone' => sprintf('555000%04d', $index),
                'direction' => $direction,
                'status' => $status,
                'message' => 'Mensaje de prueba',
            ]);
            $message->forceFill(['created_at' => $createdAt])->save();
        }

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('sentThisMonth', 3)
            ->assertSee('Enviados este mes')
            ->assertSee('Mensajes enviados este mes');
    }

    public function test_dashboard_metric_modals_show_exact_record_breakdowns(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(14, 0));
        Http::fake();
        $this->actingAs($user = User::factory()->create());

        WhatsAppMessage::create([
            'phone' => '15550000001',
            'contact_name' => 'Contacto pendiente',
            'direction' => 'inbound',
            'message_type' => 'text',
            'message' => 'Necesito consultar mi envío.',
            'status' => 'received',
            'created_at' => now()->subMinutes(20),
        ]);
        WhatsAppMessage::create([
            'phone' => '15550000001',
            'direction' => 'outbound',
            'message_type' => 'text',
            'message' => 'Estamos revisando.',
            'status' => 'delivered',
            'created_at' => now()->subMinutes(10),
        ]);
        foreach (['sent', 'delivered', 'read'] as $index => $status) {
            WhatsAppMessage::create([
                'phone' => '1555000000'.($index + 2),
                'direction' => 'outbound',
                'message_type' => 'text',
                'message' => 'Mensaje '.$status,
                'status' => $status,
                'created_at' => now()->subMinutes($index),
            ]);
        }

        PostalCampaign::create([
            'user_id' => $user->id,
            'name' => 'Campaña nacional de prueba',
            'filters' => [],
            'template' => 'Aviso de envío',
            'status' => 'running',
            'total' => 10,
            'sent' => 6,
            'failed' => 1,
            'pending' => 3,
        ]);
        PostalPackage::create([
            'source' => 'sitra',
            'external_id' => 'sitra-1',
            'tracking_number' => 'EE123456789XX',
            'current_event' => 'En tránsito',
            'last_synced_at' => now()->subHour(),
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mensajes no leídos')
            ->assertSee('Contacto pendiente')
            ->assertSee('números únicos con actividad')
            ->assertSee('Mensajes recibidos')
            ->assertSee('mensajes salientes aceptados o entregados')
            ->assertSee('Entregados')
            ->assertSee('Campaña nacional de prueba')
            ->assertSee('Pendientes: 3')
            ->assertSee('Sitra')
            ->assertSee('11/11/2026 13:00');
    }
}
