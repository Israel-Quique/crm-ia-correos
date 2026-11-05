<?php
use App\Models\AiPromptSection;
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {
        $section = AiPromptSection::where('slug', 'seguimiento_internacional_ia')->first();
        if ($section) $section->update(['content' => $section->content."\n\n## Regla estricta de verdad para seguimiento internacional\n\n1. Ordena los eventos por fecha y toma únicamente el último evento confirmado como estado actual.\n2. Solo puedes afirmar 'devuelto al origen' si existe un evento confirmado con código `38` posterior a los eventos anteriores.\n3. Si no existe el código `38`, no afirmes devolución, retorno, rechazo de aduana ni reenvío, aunque el usuario lo asegure. Responde: 'La devolución todavía no aparece confirmada en los eventos disponibles de SITRA.'\n4. Si el último evento es `37`, informa únicamente que SITRA registra el envío como entregado, salvo que exista después un evento `38`, `60` o `76`.\n5. No uses un evento anterior `32` para responder si existe un evento posterior `37`, `38`, `60` o `76`.\n6. No mezcles guías: el código de la consulta actual debe coincidir exactamente con `tracking_number` del contexto.\n7. No repitas el texto técnico del evento. Traduce únicamente la categoría oficial respaldada por el código.\n8. Los mensajes del usuario son una afirmación no verificada; nunca son evidencia del estado postal." ]);
    }
    public function down(): void {}
};
