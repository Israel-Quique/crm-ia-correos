<?php

use App\Models\AiPromptSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $prompt = AiPromptSection::where('slug', 'seguimiento_nacional_ia')->value('content');
        if ($prompt) {
            $prompt .= "\n\n### Caso específico de oficina de entrega\n\n- Si el evento dice 'Paquete recibido en oficina de entrega', 'Listo para entregar' o corresponde al código 312, clasifícalo como recibido en destino/oficina de entrega. Responde: 'Tu paquete ya llegó a la oficina de entrega o destino y está listo para entregar o recoger, según indique el sistema.' No lo describas como si apenas hubiera sido recibido por el remitente. No pidas ciudad de origen ni ofrezcas servicios adicionales si el usuario solo preguntó dónde está.\n- Si el usuario pregunta dónde está, responde directamente con la ubicación y el estado; no termines con una pregunta genérica como '¿Quieres saber más?'.";
            AiPromptSection::where('slug', 'seguimiento_nacional_ia')->update(['content' => $prompt]);
        }
    }

    public function down(): void {}
};
