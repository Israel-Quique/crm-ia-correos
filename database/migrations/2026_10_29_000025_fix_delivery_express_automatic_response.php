<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('automatic_responses')
            ->whereNull('user_id')
            ->where('type', 'quote')
            ->where('key', 'Delivery Express')
            ->update([
                'template' => 'Para cotizar tu envío nacional, primero indícame qué servicio prefieres: ¿EMS o Delivery Express? Si eliges Delivery Express, luego te consultaré la modalidad: puerta a puerta, puerta a ventanilla o ventanilla a ventanilla.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('automatic_responses')
            ->whereNull('user_id')
            ->where('type', 'quote')
            ->where('key', 'Delivery Express')
            ->update([
                'template' => 'Delivery Express es nacional, admite hasta 5 kg y requiere una modalidad válida: puerta a puerta, puerta a ventanilla o ventanilla a ventanilla.',
                'updated_at' => now(),
            ]);
    }
};
