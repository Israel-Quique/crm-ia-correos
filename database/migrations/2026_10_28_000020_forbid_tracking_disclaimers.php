<?php
use App\Models\AiPromptSection;
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {
        $section = AiPromptSection::where('slug', 'seguimiento_internacional_ia')->first();
        if ($section) $section->update(['content' => $section->content."\n\n## Regla de respuesta cuando la guía existe\n\n- Si el contexto verificado contiene la guía solicitada, responde directamente con esa información. No digas 'no puedo proporcionar información', 'no tengo información actualizada' ni recomiendes contactar otra empresa.\n- Si `destination_branch` está vacío y el contexto no registra país, ciudad u oficina, no inventes ningún lugar. No escribas Bélgica ni otro país por inferencia del código.\n- El evento 'Entregar envío (entrada)' solo debe traducirse según el catálogo oficial como Entregado. No agregues una ubicación, dirección o motivo que no aparezca en los datos verificados.\n- Una respuesta válida para una guía existente debe ser breve: indicar el estado oficial y la fecha registrada, sin advertencias contradictorias ni información externa." ]);
    }
    public function down(): void {}
};
