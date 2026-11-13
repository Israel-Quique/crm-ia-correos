<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $responses = [
            [
                'scope' => 'general',
                'type' => 'quote',
                'key' => 'Delivery Express',
                'name' => 'Cotización Delivery Express',
                'template' => '¡Claro! Delivery Express es un servicio nacional y admite paquetes de hasta 5 kg. Para confirmar la tarifa oficial necesito la ciudad de origen, la ciudad de destino, el peso y la modalidad: puerta a puerta, puerta a ventanilla o ventanilla a ventanilla. El precio depende de esos datos y te lo confirmaré con el tarifario vigente. Si pesa más de 5 kg, puedo orientarte con EMS Nacional.',
                'trigger_phrases' => [
                    'cotizar delivery express',
                    'cotización delivery express',
                    'cuánto cuesta delivery express',
                    'precio delivery express',
                    'tarifa delivery express',
                    'quiero enviar por delivery express',
                ],
            ],
            [
                'scope' => 'nacional',
                'type' => 'tracking',
                'key' => 'rastreo_nacional',
                'name' => 'Consulta de rastreo nacional',
                'template' => 'Para consultar un envío nacional, envíame el número completo de guía. Revisaré el último estado que figure en el sistema SIOP; no confirmaré una fecha estimada si no está registrada.',
                'trigger_phrases' => [
                    'rastreo nacional',
                    'seguimiento nacional',
                    'rastrear guía nacional',
                    'consultar envío nacional',
                    'consultar un envío nacional',
                    'dónde está mi paquete nacional',
                ],
            ],
            [
                'scope' => 'internacional',
                'type' => 'tracking',
                'key' => 'rastreo_internacional',
                'name' => 'Consulta de rastreo internacional',
                'template' => 'Para consultar un envío internacional, envíame el número completo de guía. Revisaré el último estado registrado en SITRA; los movimientos de aduana y las fechas estimadas solo puedo confirmarlos si aparecen en el sistema.',
                'trigger_phrases' => [
                    'rastreo internacional',
                    'seguimiento internacional',
                    'rastrear guía internacional',
                    'consultar envío internacional',
                    'consultar un envío internacional',
                    'dónde está mi paquete internacional',
                ],
            ],
        ];

        foreach ($responses as $response) {
            $triggerPhrases = $response['trigger_phrases'];
            unset($response['trigger_phrases']);

            $existing = DB::table('automatic_responses')
                ->where([
                    'scope' => $response['scope'],
                    'type' => $response['type'],
                    'key' => $response['key'],
                ])
                ->whereNull('user_id')
                ->first(['id']);

            $attributes = $response + [
                'trigger_phrases' => json_encode($triggerPhrases, JSON_UNESCAPED_UNICODE),
                'active' => true,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('automatic_responses')->where('id', $existing->id)->update($attributes);
            } else {
                DB::table('automatic_responses')->insert($attributes + [
                    'user_id' => null,
                    'created_at' => now(),
                ]);
            }
        }

    }

    public function down(): void
    {
        DB::table('automatic_responses')
            ->where('type', 'tracking')
            ->whereIn('key', ['rastreo_nacional', 'rastreo_internacional'])
            ->whereNull('user_id')
            ->delete();

        DB::table('automatic_responses')
            ->where('scope', 'general')
            ->where('type', 'quote')
            ->where('key', 'Delivery Express')
            ->whereNull('user_id')
            ->update([
                'template' => 'Para cotizar tu envío nacional, primero indícame qué servicio prefieres: ¿EMS o Delivery Express? Si eliges Delivery Express, luego te consultaré la modalidad: puerta a puerta, puerta a ventanilla o ventanilla a ventanilla.',
                'trigger_phrases' => null,
                'updated_at' => now(),
            ]);
    }
};
