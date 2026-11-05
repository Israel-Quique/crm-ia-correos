<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('postal_campaign_templates')
            ->where('scope', 'internacional')
            ->where('category', 'origin_arrival')
            ->update(['category' => 'arrival_bolivia']);

        DB::table('postal_campaign_templates')->insertOrIgnore([
            'scope' => 'internacional',
            'category' => 'departure_bolivia',
            'name' => 'Actualización de salida desde Bolivia',
            'template' => "¡Hola, {recipient}! 👋 Tenemos una actualización de tu envío internacional {tracking_number} 📦.\n\n✈️ El último evento confirma que tu envío salió de Bolivia y continúa su recorrido internacional.\n📲 Te avisaremos cuando se registre un nuevo movimiento. ¡Gracias por preferir Correos de Bolivia! 😊",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('postal_campaign_templates')
            ->where('scope', 'internacional')
            ->where('category', 'departure_bolivia')
            ->delete();

        DB::table('postal_campaign_templates')
            ->where('scope', 'internacional')
            ->where('category', 'arrival_bolivia')
            ->update(['category' => 'origin_arrival']);
    }
};
