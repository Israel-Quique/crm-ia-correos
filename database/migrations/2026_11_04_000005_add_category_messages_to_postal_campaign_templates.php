<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postal_campaign_templates', function (Blueprint $table): void {
            $table->string('category', 40)->nullable()->after('scope');
            $table->unique(['scope', 'category']);
        });

        $now = now();
        $messages = [
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

        foreach ($messages as $scope => $categories) {
            foreach ($categories as $category => [$name, $template]) {
                DB::table('postal_campaign_templates')->insert([
                    'scope' => $scope,
                    'category' => $category,
                    'name' => $name,
                    'template' => $template,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('postal_campaign_templates')->whereNotNull('category')->delete();

        Schema::table('postal_campaign_templates', function (Blueprint $table): void {
            $table->dropUnique('postal_campaign_templates_scope_category_unique');
            $table->dropColumn('category');
        });
    }
};
