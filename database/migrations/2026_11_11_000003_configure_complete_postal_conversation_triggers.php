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
                'key' => 'cotizacion_postal',
                'name' => 'Cotización postal',
                'template' => '📦 ¡Con gusto te ayudo con una cotización! Indícame la ciudad de origen y destino, y si prefieres EMS o Delivery Express. Para Delivery Express también necesitaré la modalidad y el peso; EMS se calcula con el destino y el peso. Te confirmaré el precio únicamente después de consultar el tarifario vigente. ✉️',
                'trigger_phrases' => [
                    'cotizar un envio',
                    'cotizacion postal',
                    'precio de envio',
                    'cuanto cuesta enviar',
                    'cuanto sale enviar',
                    'quiero cotizar',
                    'quiero enviar un paquete',
                    'tarifa postal',
                ],
            ],
            [
                'scope' => 'general',
                'type' => 'tracking',
                'key' => 'rastreo_postal',
                'name' => 'Consulta de rastreo postal',
                'template' => '🔎 Para consultar tu paquete, envíame el número completo de guía. Revisaré el registro correspondiente en SIOP (envíos nacionales) o SITRA (envíos internacionales) y te informaré el último estado disponible, junto con la fecha o destino si aparecen registrados. 📦',
                'trigger_phrases' => [
                    'rastreo de paquete',
                    'rastrear mi paquete',
                    'seguimiento de paquete',
                    'donde esta mi paquete',
                    'consultar mi guia',
                    'ubicacion de mi envio',
                    'quiero rastrear',
                    'numero de seguimiento',
                ],
            ],
        ];

        foreach ($responses as $response) {
            $triggerPhrases = $response['trigger_phrases'];
            unset($response['trigger_phrases']);

            $existing = DB::table('automatic_responses')
                ->where('scope', $response['scope'])
                ->where('type', $response['type'])
                ->where('key', $response['key'])
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

        $triggerPhrases = [
            'EMS NAT' => [
                'cotizar ems nacional',
                'cotizacion ems nacional',
                'precio ems nacional',
                'tarifa ems nat',
                'ems nacional',
                'ems nat',
            ],
            'EMS INT' => [
                'cotizar ems internacional',
                'cotizacion ems internacional',
                'precio ems internacional',
                'tarifa ems int',
                'ems internacional',
                'ems int',
            ],
            'Delivery Express' => [
                'cotizar delivery express',
                'cotizacion delivery express',
                'cuanto cuesta delivery express',
                'precio delivery express',
                'tarifa delivery express',
                'quiero enviar por delivery express',
            ],
        ];

        foreach ($triggerPhrases as $key => $phrases) {
            DB::table('automatic_responses')
                ->where('scope', 'general')
                ->where('type', 'quote')
                ->where('key', $key)
                ->whereNull('user_id')
                ->update([
                    'trigger_phrases' => json_encode($phrases, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }

        foreach ([
            'rastreo_nacional' => [
                'rastreo nacional',
                'seguimiento nacional',
                'rastrear guia nacional',
                'consultar envio nacional',
                'consultar un envio nacional',
                'donde esta mi paquete nacional',
            ],
            'rastreo_internacional' => [
                'rastreo internacional',
                'seguimiento internacional',
                'rastrear guia internacional',
                'consultar envio internacional',
                'consultar un envio internacional',
                'donde esta mi paquete internacional',
            ],
        ] as $key => $phrases) {
            DB::table('automatic_responses')
                ->where('type', 'tracking')
                ->where('key', $key)
                ->whereNull('user_id')
                ->update([
                    'trigger_phrases' => json_encode($phrases, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('automatic_responses')
            ->whereNull('user_id')
            ->whereIn('key', ['cotizacion_postal', 'rastreo_postal'])
            ->whereIn('type', ['quote', 'tracking'])
            ->delete();

        DB::table('automatic_responses')
            ->whereNull('user_id')
            ->where('scope', 'general')
            ->where('type', 'quote')
            ->whereIn('key', ['EMS NAT', 'EMS INT', 'Delivery Express'])
            ->update(['trigger_phrases' => null, 'updated_at' => now()]);

        DB::table('automatic_responses')
            ->whereNull('user_id')
            ->where('type', 'tracking')
            ->whereIn('key', ['rastreo_nacional', 'rastreo_internacional'])
            ->update(['trigger_phrases' => null, 'updated_at' => now()]);
    }
};
