<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $messages = [
            'origin_arrival' => [
                'Actualización de llegada del envío',
                "¡Hola, {recipient}! 👋 Buenas noticias sobre tu envío 📦.\n\nLa guía {tracking_number} ya llegó a Bolivia 🇧🇴 y continúa su proceso de clasificación y distribución 📬.\n📍 Todavía no aparece una sucursal de destino ni disponibilidad para recojo.\n📲 Te avisaremos cuando se registre un nuevo movimiento. ¡Gracias por preferir Correos de Bolivia! 😊",
                'Actualización de llegada del envío',
                'Hola {recipient}, tu envío internacional {tracking_number} ya llegó a Bolivia y continúa su proceso de clasificación y distribución. Todavía no figura una sucursal de destino ni disponibilidad para recojo. Te avisaremos cuando registre un nuevo movimiento.',
            ],
            'customs' => [
                'Actualización de revisión aduanera',
                "¡Hola, {recipient}! 👋 Tenemos una actualización de tu envío internacional {tracking_number} 📦.\n\n🛃 Tu envío se encuentra en proceso aduanero.\n📲 Te notificaremos cuando el seguimiento registre un nuevo movimiento. ¡Gracias por preferir Correos de Bolivia! 😊",
                'Actualización de revisión aduanera',
                'Hola {recipient}, tu envío internacional {tracking_number} se encuentra en proceso aduanero. La duración puede variar según la revisión; te notificaremos cuando el seguimiento registre un nuevo movimiento.',
            ],
            'national_distribution' => [
                'En proceso de distribución nacional',
                "¡Hola, {recipient}! 👋 Tenemos una actualización de tu envío {tracking_number} 📦.\n\n🇧🇴 Tu envío continúa en proceso de clasificación y distribución dentro de Bolivia.\n📲 Te avisaremos cuando se registre un nuevo movimiento. ¡Gracias por preferir Correos de Bolivia! 😊",
                'En proceso de distribución nacional',
                'Hola {recipient}, tu envío {tracking_number} continúa en proceso de clasificación y distribución dentro de Bolivia. Te avisaremos cuando se registre una actualización.',
            ],
            'last_mile' => [
                'En distribución hacia el destino',
                "¡Hola, {recipient}! 👋 Tenemos una actualización de tu envío {tracking_number} 📦.\n\n🚚 Tu envío se encuentra en la etapa de distribución local hacia {destination_branch}.\n📲 Te notificaremos cuando registre un nuevo movimiento. ¡Gracias por preferir Correos de Bolivia! 😊",
                'En distribución hacia el destino',
                'Hola {recipient}, tu envío {tracking_number} avanza en la distribución hacia su destino. Te notificaremos cuando la oficina registre el siguiente movimiento.',
            ],
            'pickup' => [
                'Envío disponible para recojo',
                "¡Hola, {recipient}! 👋 Tu envío {tracking_number} está disponible para recojo 📦.\n\n🏤 Puedes recogerlo en {destination_branch}.\n🪪 Presenta tu documento de identidad original y consulta el horario de atención de la sucursal.\n🙏 ¡Te esperamos!",
                'Envío disponible para recojo',
                'Hola {recipient}, tu envío internacional {tracking_number} está disponible para recoger en {destination_branch}. Acércate en el horario de atención de la sucursal y presenta tu documento de identidad original.',
            ],
            'courier' => [
                'Envío asignado a cartero',
                "¡Hola, {recipient}! 👋 Tenemos una actualización de tu envío {tracking_number} 📦.\n\n🚴‍♂️ Tu envío fue asignado a un cartero y se encuentra en proceso de distribución.\n📲 Te avisaremos cuando se registre un nuevo movimiento. ¡Gracias por preferir Correos de Bolivia! 😊",
                'Envío asignado a cartero',
                'Hola {recipient}, tu envío internacional {tracking_number} fue asignado a reparto por cartero. Te avisaremos cuando registre un nuevo movimiento.',
            ],
            'delivered' => [
                'Confirmación de entrega internacional',
                "¡Hola, {recipient}! 👋\n\n✅ El seguimiento de tu envío internacional {tracking_number} registra que fue entregado.\n💬 Gracias por preferir Correos de Bolivia. 😊",
                'Confirmación de entrega internacional',
                'Hola {recipient}, el seguimiento de tu envío internacional {tracking_number} registra que fue entregado. Gracias por preferir Correos de Bolivia.',
            ],
            'import_stopped' => [
                'Actualización sobre el proceso de importación',
                "¡Hola, {recipient}! 👋 Tenemos una actualización de tu envío {tracking_number} 📦.\n\n🛃 El seguimiento indica una detención en el proceso de importación.\n☎️ Para conocer el motivo y los pasos a seguir, consulta con Correos de Bolivia e indica tu número de guía.",
                'Actualización sobre el proceso de importación',
                'Hola {recipient}, tu envío {tracking_number} registra una detención en el proceso de importación. Para conocer los detalles y los pasos a seguir, consulta con la oficina de Correos de Bolivia.',
            ],
            'other' => [
                'Actualización de envío internacional',
                "¡Hola, {recipient}! 👋 Tenemos una actualización de tu envío internacional {tracking_number} 📦.\n\n📍 Estado actual: {current_event}.\n📲 Te avisaremos cuando se registre un nuevo movimiento. ¡Gracias por preferir Correos de Bolivia! 😊",
                'Actualización de envío internacional',
                'Hola {recipient}, tu envío internacional {tracking_number} tiene una nueva actualización: {current_event}. Te avisaremos cuando se registre otro movimiento.',
            ],
        ];

        foreach ($messages as $category => [$name, $template, $previousName, $previousTemplate]) {
            DB::table('postal_campaign_templates')
                ->where('scope', 'internacional')
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

    public function down(): void
    {
    }
};
