<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        // WhatsApp debe responder mediante el árbol de decisión. Ollama
        // continúa disponible para el asistente interno, pero no como
        // fallback del autorespondedor de WhatsApp.
        DB::table('app_settings')->updateOrInsert(
            ['key' => 'ai_enabled'],
            ['value' => '0', 'updated_at' => $now, 'created_at' => $now],
        );

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'automatic_responses_enabled'],
            ['value' => '1', 'updated_at' => $now, 'created_at' => $now],
        );

        foreach (['automatic_decision_flow_quote', 'automatic_decision_flow_tracking'] as $key) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => '1', 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    public function down(): void
    {
        // No se reactiva Ollama automáticamente al revertir una migración.
    }
};
