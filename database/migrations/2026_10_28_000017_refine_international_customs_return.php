<?php

use App\Models\AiPromptSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $prompt = AiPromptSection::where('slug', 'seguimiento_internacional_ia')->value('content');
        if ($prompt) {
            $prompt .= "\n\n### Aduana del país de destino y retorno al origen\n\n- El código `38` ('Devolver envío desde aduana (entrada)') significa que el envío fue devuelto desde la aduana del país de destino hacia su origen. No lo describas como una devolución causada necesariamente por la aduana boliviana.\n- Si el historial confirma salida normal desde Bolivia, llegada al país de destino y luego devolución desde aduana en entrada, explica que la aduana del país destino devolvió el envío al origen.\n- No inventes el motivo de la devolución. Indica únicamente que el motivo no está registrado si la API no lo proporciona.\n- Si existe un evento de devolución posterior, ese evento tiene prioridad sobre cualquier entrega o tránsito anterior.\n- Solo clasifica como entregado cuando el último evento confirmado sea el código `37` y no exista después un evento de devolución, cancelación o importación detenida.";
            AiPromptSection::where('slug', 'seguimiento_internacional_ia')->update(['content' => $prompt]);
        }
    }

    public function down(): void {}
};
