<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('postal_campaign_templates')
            ->where('scope', 'internacional')
            ->where('category', 'international')
            ->exists();

        if ($existing) {
            return;
        }

        $fallback = DB::table('postal_campaign_templates')
            ->where('scope', 'internacional')
            ->where('category', 'other')
            ->first(['name', 'template']);

        DB::table('postal_campaign_templates')->insert([
            'scope' => 'internacional',
            'category' => 'international',
            'name' => 'Actualización internacional general',
            'template' => $fallback?->template
                ?? '¡Hola, {recipient}! 👋 Tenemos una actualización de tu envío internacional {tracking_number} 📦. Estado actual: {current_event}. Te avisaremos cuando se registre un nuevo movimiento. 😊',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('postal_campaign_templates')
            ->where('scope', 'internacional')
            ->where('category', 'international')
            ->delete();
    }
};
