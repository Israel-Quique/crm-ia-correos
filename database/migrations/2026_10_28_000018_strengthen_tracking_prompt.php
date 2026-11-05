<?php
use App\Models\AiPromptSection;
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {
        foreach (['seguimiento_nacional_ia','seguimiento_internacional_ia'] as $slug) {
            $section = AiPromptSection::where('slug', $slug)->first();
            if ($section) $section->update(['content' => $section->content."\n\n## Regla anti-alucinación absoluta\n- La guía del mensaje actual es la única guía válida. Ignora cualquier guía anterior de la conversación para responder el estado actual.\n- Si la guía existe en el contexto verificado, responde con esos datos; nunca digas que no hay información.\n- No copies eventos, actores o estados de otra guía.\n- No conviertas un evento anterior en el estado actual.\n- No agregues direcciones, fechas estimadas, causas, reenvíos, pagos o promesas que no estén registrados.\n- Si los datos no permiten confirmar algo, dilo claramente en lugar de completarlo."]);
        }
    }
    public function down(): void {}
};
