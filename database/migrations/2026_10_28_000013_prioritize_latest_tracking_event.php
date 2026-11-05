<?php

use App\Models\AiPromptSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $prompt = AiPromptSection::where('slug', 'seguimiento_nacional_ia')->value('content');
        if ($prompt) {
            $prompt .= "\n\n### Prioridad del último evento\n\n- El último evento registrado por fecha es el único estado actual del paquete. Si el historial contiene eventos anteriores distintos, no los uses para describir dónde está ahora.\n- Para CUALQUIER respuesta de estado, usa primero `current_event` y `current_event_at`. Solo resume eventos anteriores cuando el usuario pregunte expresamente por el recorrido.\n- Ejemplo: si el último evento es 'Paquete recibido en oficina de entrega (Listo para entregar)' con código 312, responde que ya llegó a la oficina de entrega/destino y está listo para entregar o recoger. No respondas usando un evento anterior como 'enviado en saca interna'.";
            AiPromptSection::where('slug', 'seguimiento_nacional_ia')->update(['content' => $prompt]);
        }
        $international = AiPromptSection::where('slug', 'seguimiento_internacional_ia')->value('content');
        if ($international) {
            $international .= "\n\n### Prioridad del último evento\n\n- El último evento registrado por fecha es el único estado actual. Usa `current_event` y `current_event_at` para responder la situación presente; los eventos anteriores solo se resumen si el usuario pregunta por el recorrido.";
            AiPromptSection::where('slug', 'seguimiento_internacional_ia')->update(['content' => $international]);
        }
    }

    public function down(): void {}
};
