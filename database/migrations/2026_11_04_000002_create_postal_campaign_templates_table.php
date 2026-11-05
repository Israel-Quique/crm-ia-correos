<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postal_campaign_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->text('template');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('postal_campaign_templates')->insert([
            [
                'name' => 'Notificación estándar de estado',
                'template' => 'Hola {recipient}, tu paquete {tracking_number} está en estado {current_event}.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Aviso formal de rastreo',
                'template' => 'Estimado/a {recipient}, le informamos que su envío {tracking_number} ha cambiado a: {current_event}. Gracias por su preferencia.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Aviso de recogida / listo',
                'template' => '¡Hola {recipient}! Tu pedido {tracking_number} ya se encuentra listo y en {current_event}. ¡Te esperamos!',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Aviso de incidencia o urgencia',
                'template' => 'Urgente {recipient}: Por favor revisar el paquete {tracking_number}. Estado actual: {current_event}. Contáctanos para coordinar la entrega.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_campaign_templates');
    }
};
