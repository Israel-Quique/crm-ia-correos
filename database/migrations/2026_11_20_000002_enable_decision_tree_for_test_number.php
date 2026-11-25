<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $phone = preg_replace('/\D+/', '', (string) env('WHATSAPP_TEST_MODE_NUMBER', '59164229403')) ?: '59164229403';

        DB::table('whatsapp_conversation_settings')->updateOrInsert(
            ['phone' => $phone],
            ['mode' => 'automatic', 'updated_at' => $now, 'created_at' => $now],
        );
    }

    public function down(): void
    {
        // No se cambia el modo del contacto al revertir esta migración.
    }
};
