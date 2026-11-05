<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $previousDefaults = [
            'nacional' => [
                'origin' => ['Confirmación de admisión en origen', 'Hola {recipient}, tu paquete {tracking_number} ha sido registrado exitosamente en la oficina de origen.'],
                'transit' => ['Actualización de envío en camino', 'Hola {recipient}, tu paquete {tracking_number} se encuentra en camino hacia su destino. Te avisaremos cuando llegue.'],
                'destination' => ['Paquete recibido en destino', 'Hola {recipient}, tu paquete {tracking_number} ya llegó a la oficina de destino y continúa su proceso de entrega.'],
                'pickup' => ['Paquete disponible en ventanilla', 'Hola {recipient}, tu paquete {tracking_number} está disponible para recoger en ventanilla. Por favor, presenta tu documento de identidad.'],
                'courier' => ['Paquete asignado a cartero', 'Hola {recipient}, tu paquete {tracking_number} fue asignado a un cartero para su entrega.'],
                'delivered' => ['Confirmación de entrega', 'Hola {recipient}, el paquete {tracking_number} figura como entregado. Gracias por elegirnos.'],
                'cancelled' => ['Actualización de envío cancelado', 'Hola {recipient}, el envío {tracking_number} figura como cancelado o devuelto. Para más información, comunícate con nuestra oficina.'],
            ],
            'internacional' => [
                'origin_arrival' => ['Actualización de origen y llegada', 'Hola {recipient}, tu envío internacional {tracking_number} registra una actualización en su recorrido: {current_event}.'],
                'customs' => ['Actualización de aduana', 'Hola {recipient}, tu envío {tracking_number} está en proceso de revisión aduanera. Te informaremos cuando avance al siguiente paso.'],
                'national_distribution' => ['En distribución nacional', 'Hola {recipient}, tu envío {tracking_number} continúa su distribución dentro del país.'],
                'last_mile' => ['En proceso de entrega local', 'Hola {recipient}, tu envío {tracking_number} está en la etapa final de distribución hacia su destino.'],
                'pickup' => ['Envío disponible en ventanilla', 'Hola {recipient}, tu envío {tracking_number} está disponible para recoger en la oficina indicada. Presenta tu documento de identidad.'],
                'courier' => ['Envío asignado a cartero', 'Hola {recipient}, tu envío {tracking_number} fue asignado a distribución por cartero.'],
                'delivered' => ['Confirmación de entrega internacional', 'Hola {recipient}, el envío internacional {tracking_number} figura como entregado.'],
                'import_stopped' => ['Aviso sobre envío detenido en importación', 'Hola {recipient}, tu envío {tracking_number} registra una detención en el proceso de importación. Comunícate con nuestra oficina para recibir orientación.'],
                'other' => ['Actualización de envío internacional', 'Hola {recipient}, tu envío {tracking_number} tiene una actualización: {current_event}.'],
            ],
        ];

        $messages = [
            'nacional' => [
                'origin' => [
                    'Confirmación de admisión en origen',
                    'Hola {recipient}, tu paquete {tracking_number} fue recibido en la oficina de origen. Te avisaremos cuando salga en ruta hacia su destino.',
                ],
                'transit' => [
                    'Actualización de envío en camino',
                    'Hola {recipient}, tu paquete {tracking_number} ya está en camino hacia {destination_branch}. Te notificaremos cuando registre un nuevo movimiento.',
                ],
                'destination' => [
                    'Paquete recibido en destino',
                    'Hola {recipient}, tu paquete {tracking_number} ya llegó a la sucursal de destino ({destination_branch}). El siguiente paso será reparto o disponibilidad para recojo, según el proceso de la regional.',
                ],
                'pickup' => [
                    'Paquete disponible para recojo',
                    'Hola {recipient}, tienes correspondencia pendiente de entrega: tu paquete {tracking_number} está disponible en {destination_branch}. Acércate en el horario de atención de la sucursal y presenta tu documento de identidad original. ¡Te esperamos!',
                ],
                'courier' => [
                    'Paquete asignado a cartero',
                    'Hola {recipient}, tu paquete {tracking_number} fue asignado a un cartero y está en proceso de distribución. Te avisaremos cuando registre un nuevo movimiento.',
                ],
                'delivered' => [
                    'Confirmación de entrega',
                    'Hola {recipient}, el seguimiento de tu paquete {tracking_number} registra que fue entregado. Gracias por preferir Correos de Bolivia.',
                ],
                'cancelled' => [
                    'Actualización de envío cancelado o devuelto',
                    'Hola {recipient}, el último estado de tu envío {tracking_number} indica que fue cancelado o devuelto. Para conocer el motivo y los pasos a seguir, consulta con la oficina de Correos de Bolivia.',
                ],
            ],
            'internacional' => [
                'origin_arrival' => [
                    'Actualización de llegada del envío',
                    'Hola {recipient}, tu envío internacional {tracking_number} ya llegó a Bolivia y continúa su proceso de clasificación y distribución. Todavía no figura una sucursal de destino ni disponibilidad para recojo. Te avisaremos cuando registre un nuevo movimiento.',
                ],
                'customs' => [
                    'Actualización de revisión aduanera',
                    'Hola {recipient}, tu envío internacional {tracking_number} se encuentra en proceso aduanero. La duración puede variar según la revisión; te notificaremos cuando el seguimiento registre un nuevo movimiento.',
                ],
                'national_distribution' => [
                    'En proceso de distribución nacional',
                    'Hola {recipient}, tu envío {tracking_number} continúa en proceso de clasificación y distribución dentro de Bolivia. Te avisaremos cuando se registre una actualización.',
                ],
                'last_mile' => [
                    'En distribución hacia el destino',
                    'Hola {recipient}, tu envío {tracking_number} avanza en la distribución hacia su destino. Te notificaremos cuando la oficina registre el siguiente movimiento.',
                ],
                'pickup' => [
                    'Envío disponible para recojo',
                    'Hola {recipient}, tu envío internacional {tracking_number} está disponible para recoger en {destination_branch}. Acércate en el horario de atención de la sucursal y presenta tu documento de identidad original.',
                ],
                'courier' => [
                    'Envío asignado a cartero',
                    'Hola {recipient}, tu envío internacional {tracking_number} fue asignado a reparto por cartero. Te avisaremos cuando registre un nuevo movimiento.',
                ],
                'delivered' => [
                    'Confirmación de entrega internacional',
                    'Hola {recipient}, el seguimiento de tu envío internacional {tracking_number} registra que fue entregado. Gracias por preferir Correos de Bolivia.',
                ],
                'import_stopped' => [
                    'Actualización sobre el proceso de importación',
                    'Hola {recipient}, tu envío {tracking_number} registra una detención en el proceso de importación. Para conocer los detalles y los pasos a seguir, consulta con la oficina de Correos de Bolivia.',
                ],
                'other' => [
                    'Actualización de envío internacional',
                    'Hola {recipient}, tu envío internacional {tracking_number} tiene una nueva actualización: {current_event}. Te avisaremos cuando se registre otro movimiento.',
                ],
            ],
        ];

        foreach ($messages as $scope => $categories) {
            foreach ($categories as $category => [$name, $template]) {
                [$previousName, $previousTemplate] = $previousDefaults[$scope][$category];

                DB::table('postal_campaign_templates')
                    ->where('scope', $scope)
                    ->where('category', $category)
                    ->where('name', $previousName)
                    ->where('template', $previousTemplate)
                    ->update([
                        'name' => $name,
                        'template' => $template,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
    }
};
