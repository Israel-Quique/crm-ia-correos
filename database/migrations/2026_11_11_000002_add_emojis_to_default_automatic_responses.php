<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const TEMPLATES = [
        ['general', 'event', 'saludo', '👋 ¡Hola! Soy el asistente de Correos de Bolivia. ¿Deseas cotizar un envío o rastrear un paquete? 😊'],
        ['general', 'quote', 'EMS NAT', '📦 Para cotizar EMS Nacional, indícame la ciudad de origen, la ciudad de destino y el peso del paquete. Consultaré el tarifario vigente y te compartiré únicamente una tarifa confirmada. ✉️'],
        ['general', 'quote', 'EMS INT', '🌎 Para cotizar EMS Internacional, indícame el país de origen, el país de destino y el peso del paquete. Te confirmaré el precio solo cuando encuentre una tarifa vigente. ✉️'],
        ['general', 'quote', 'Delivery Express', '🚚 ¡Claro! Delivery Express es un servicio nacional para paquetes de hasta 5 kg. Para consultar la tarifa oficial, indícame la ciudad de origen y destino, el peso y la modalidad: puerta a puerta, puerta a ventanilla o ventanilla a ventanilla. El precio depende de esos datos y lo confirmaré con el tarifario vigente. 📦 Si pesa más de 5 kg, puedo orientarte con EMS Nacional.'],
        ['nacional', 'tracking', 'rastreo_nacional', '🔎 Para consultar tu envío nacional, envíame el número completo de guía. Revisaré el último estado registrado en SIOP y te compartiré la información disponible. No indicaré una fecha estimada si no figura en el sistema. 📦'],
        ['internacional', 'tracking', 'rastreo_internacional', '🌎 Para consultar tu envío internacional, envíame el número completo de guía. Revisaré el último estado registrado en SITRA; los movimientos de aduana y las fechas estimadas solo puedo confirmarlos si aparecen en el sistema. 📦'],
    ];

    public function up(): void
    {
        foreach (self::TEMPLATES as [$scope, $type, $key, $template]) {
            DB::table('automatic_responses')
                ->where('scope', $scope)
                ->where('type', $type)
                ->where('key', $key)
                ->whereNull('user_id')
                ->update([
                    'template' => $template,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $templates = [
            ['general', 'event', 'saludo', '¡Hola! 👋 Soy el asistente de Correos de Bolivia. ¿Deseas cotizar un envío o rastrear un paquete? 😊'],
            ['general', 'quote', 'EMS NAT', 'Para cotizar EMS nacional necesito origen, destino y peso. El precio se mostrará únicamente cuando exista una tarifa verificada.'],
            ['general', 'quote', 'EMS INT', 'Para cotizar EMS internacional necesito país de origen, país de destino y peso. El precio se mostrará únicamente cuando exista una tarifa verificada.'],
            ['general', 'quote', 'Delivery Express', '¡Claro! Delivery Express es un servicio nacional y admite paquetes de hasta 5 kg. Para confirmar la tarifa oficial necesito la ciudad de origen, la ciudad de destino, el peso y la modalidad: puerta a puerta, puerta a ventanilla o ventanilla a ventanilla. El precio depende de esos datos y te lo confirmaré con el tarifario vigente. Si pesa más de 5 kg, puedo orientarte con EMS Nacional.'],
            ['nacional', 'tracking', 'rastreo_nacional', 'Para consultar un envío nacional, envíame el número completo de guía. Revisaré el último estado que figure en el sistema SIOP; no confirmaré una fecha estimada si no está registrada.'],
            ['internacional', 'tracking', 'rastreo_internacional', 'Para consultar un envío internacional, envíame el número completo de guía. Revisaré el último estado registrado en SITRA; los movimientos de aduana y las fechas estimadas solo puedo confirmarlos si aparecen en el sistema.'],
        ];

        foreach ($templates as [$scope, $type, $key, $template]) {
            DB::table('automatic_responses')
                ->where('scope', $scope)
                ->where('type', $type)
                ->where('key', $key)
                ->whereNull('user_id')
                ->update([
                    'template' => $template,
                    'updated_at' => now(),
                ]);
        }
    }
};
