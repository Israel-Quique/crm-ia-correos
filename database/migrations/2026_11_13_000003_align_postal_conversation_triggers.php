<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const TRIGGER_ADDITIONS = [
        ['general', 'quote', 'cotizacion_postal', [
            'cotizar paquete',
            'cotizar mi envio',
            'consultar tarifa postal',
            'precio del envio',
            'costo de envio',
            'cotizar envio postal',
        ]],
        ['general', 'quote', 'EMS NAT', [
            'cotizar ems nat',
            'precio ems nat',
            'envio ems nacional',
            'cotizar envio dentro de bolivia por ems',
        ]],
        ['general', 'quote', 'EMS INT', [
            'cotizar ems int',
            'precio ems int',
            'envio ems internacional',
            'cotizar envio al exterior por ems',
        ]],
        ['general', 'quote', 'Delivery Express', [
            'cotizar por delivery express',
            'envio nacional delivery express',
            'tarifa para delivery express',
            'paquete por delivery express',
        ]],
        ['general', 'tracking', 'rastreo_postal', [
            'rastrear paquete',
            'estado de mi envio',
            'estado de mi guia',
            'consultar estado del paquete',
            'donde esta mi envio',
            'buscar mi paquete',
        ]],
        ['nacional', 'tracking', 'rastreo_nacional', [
            'rastrear mi paquete nacional',
            'estado de mi envio nacional',
            'consultar guia nacional',
            'seguimiento de mi paquete nacional',
            'donde esta mi envio nacional',
        ]],
        ['internacional', 'tracking', 'rastreo_internacional', [
            'rastrear mi paquete internacional',
            'estado de mi envio internacional',
            'consultar guia internacional',
            'seguimiento de mi paquete internacional',
            'donde esta mi envio internacional',
        ]],
        ['nacional', 'event', 'recibido_origen', [
            'recibido en origen',
            'admitido en origen',
            'sucursal de origen',
        ]],
        ['nacional', 'event', 'en_camino', [
            'en transito',
            'despachado',
            'en ruta',
        ]],
        ['nacional', 'event', 'recibido_destino', [
            'recibido en destino',
            'llegada a destino',
            'sucursal de destino',
        ]],
        ['nacional', 'event', 'cartero', [
            'asignado a cartero',
            'en reparto',
            'salida a reparto',
        ]],
        ['nacional', 'event', 'entregado', [
            'entrega realizada',
            'recibido por destinatario',
        ]],
        ['nacional', 'event', 'cancelado', [
            'devuelto al origen',
            'devolucion al remitente',
            'envio cancelado',
        ]],
        ['internacional', 'event', 'origen_llegada', [
            'aceptado en origen',
            'despacho internacional',
            'oficina de intercambio',
        ]],
        ['internacional', 'event', 'distribucion_nacional', [
            'distribucion nacional',
            'llegada al pais de destino',
        ]],
        ['internacional', 'event', 'aduana', [
            'en proceso de aduana',
            'retenido en aduana',
            'despacho aduanero',
        ]],
        ['internacional', 'event', 'ultima_milla', [
            'ultima milla',
            'oficina de entrega',
            'etapa final de distribucion',
        ]],
        ['internacional', 'event', 'entregado', [
            'entrega internacional realizada',
            'entregado en destino',
        ]],
        ['internacional', 'event', 'importacion_detenida', [
            'devuelto desde aduana',
            'importacion detenida',
            'retorno al origen',
        ]],
    ];

    private const GENERIC_QUOTE_OLD_TEMPLATE = '📦 ¡Con gusto te ayudo con una cotización! Indícame la ciudad de origen y destino, y si prefieres EMS o Delivery Express. Para Delivery Express también necesitaré la modalidad y el peso; EMS se calcula con el destino y el peso. Te confirmaré el precio únicamente después de consultar el tarifario vigente. ✉️';

    private const GENERIC_QUOTE_TEMPLATE = '📦 ¡Claro! Te ayudo a cotizar el envío. El precio se confirma únicamente con el tarifario vigente. ✉️';

    private const EVENT_RESPONSES = [
        [
            'nacional',
            'ventanilla',
            'Disponible en ventanilla',
            '📍 La guía {tracking_number} figura disponible para su recojo en ventanilla, según el último evento registrado.',
            ['disponible en ventanilla', 'ventanilla para recojo', 'listo para recojo', 'disponible para retiro'],
        ],
        [
            'internacional',
            'cartero',
            'Asignado a cartero internacional',
            '🚴‍♂️ La guía {tracking_number} fue asignada a un cartero y se encuentra en proceso de distribución, según el último evento registrado en SITRA.',
            ['asignado a cartero', 'reparto por cartero', 'en reparto', 'cartero internacional'],
        ],
        [
            'internacional',
            'ventanilla',
            'Disponible en ventanilla internacional',
            '🌎 La guía {tracking_number} figura disponible para su recojo en ventanilla, según el último evento registrado en SITRA.',
            ['disponible en ventanilla', 'ventanilla para recojo', 'listo para recojo', 'disponible para retiro'],
        ],
    ];

    public function up(): void
    {
        foreach (self::TRIGGER_ADDITIONS as [$scope, $type, $key, $phrases]) {
            $this->mergeTriggers($scope, $type, $key, $phrases);
        }

        foreach (self::EVENT_RESPONSES as [$scope, $key, $name, $template, $phrases]) {
            $this->ensureEventResponse($scope, $key, $name, $template, $phrases);
        }

        DB::table('automatic_responses')
            ->where('scope', 'general')
            ->where('type', 'quote')
            ->where('key', 'cotizacion_postal')
            ->whereNull('user_id')
            ->where('template', self::GENERIC_QUOTE_OLD_TEMPLATE)
            ->update([
                'template' => self::GENERIC_QUOTE_TEMPLATE,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('automatic_responses')
            ->where('scope', 'general')
            ->where('type', 'quote')
            ->where('key', 'cotizacion_postal')
            ->whereNull('user_id')
            ->where('template', self::GENERIC_QUOTE_TEMPLATE)
            ->update([
                'template' => self::GENERIC_QUOTE_OLD_TEMPLATE,
                'updated_at' => now(),
            ]);

        foreach (self::EVENT_RESPONSES as [$scope, $key, $name, $template]) {
            DB::table('automatic_responses')
                ->where('scope', $scope)
                ->where('type', 'event')
                ->where('key', $key)
                ->where('name', $name)
                ->where('template', $template)
                ->whereNull('user_id')
                ->delete();
        }
    }

    private function mergeTriggers(string $scope, string $type, string $key, array $phrases): void
    {
        $response = DB::table('automatic_responses')
            ->where('scope', $scope)
            ->where('type', $type)
            ->where('key', $key)
            ->whereNull('user_id')
            ->first(['id', 'trigger_phrases']);

        if ($response === null) {
            return;
        }

        $existing = json_decode((string) $response->trigger_phrases, true);
        $existing = is_array($existing) ? $existing : [];
        $triggers = array_values(array_unique(array_merge($existing, $phrases)));

        DB::table('automatic_responses')
            ->where('id', $response->id)
            ->update([
                'trigger_phrases' => json_encode($triggers, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    private function ensureEventResponse(string $scope, string $key, string $name, string $template, array $phrases): void
    {
        $response = DB::table('automatic_responses')
            ->where('scope', $scope)
            ->where('type', 'event')
            ->where('key', $key)
            ->whereNull('user_id')
            ->first(['id']);

        if ($response !== null) {
            $this->mergeTriggers($scope, 'event', $key, $phrases);

            return;
        }

        DB::table('automatic_responses')->insert([
            'user_id' => null,
            'scope' => $scope,
            'type' => 'event',
            'key' => $key,
            'name' => $name,
            'template' => $template,
            'trigger_phrases' => json_encode($phrases, JSON_UNESCAPED_UNICODE),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
