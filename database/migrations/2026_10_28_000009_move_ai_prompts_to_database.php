<?php

use App\Models\AiPromptSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $general = DB::table('ai_prompt_sections')->where('slug', 'cotizaciones_ia')->value('content')
            ?? 'Atiende cotizaciones postales y seguimiento de paquetes. Responde en español, con claridad y sin inventar datos.';

        AiPromptSection::updateOrCreate(
            ['slug' => 'general_system'],
            ['name' => 'Prompt general del sistema', 'content' => $general, 'active' => true],
        );

        $prompts = [
            'cotizaciones_ia' => "Reglas específicas de cotización:\n- EMS permite envíos nacionales, internacionales y a provincias.\n- Delivery Express solo es nacional y admite hasta 5 kg.\n- Solicita origen, destino, servicio y peso cuando falten.\n- Delivery Express requiere una modalidad válida.\n- Nunca inventes tarifas; usa únicamente precios verificados por el tarifario.",
            'seguimiento_nacional_ia' => "Reglas específicas de seguimiento nacional:\n- Consulta únicamente guías nacionales existentes de SIOP.\n- Solicita el número de guía si falta.\n- Informa estado, destino, fecha y eventos verificados.\n- No inventes fechas, estados, sucursales ni tiempos de entrega.",
            'seguimiento_internacional_ia' => "Reglas específicas de seguimiento internacional:\n- Consulta únicamente guías internacionales existentes de SITRA.\n- Solicita el número de guía si falta.\n- Informa estado, destino, fecha y eventos verificados.\n- Explica aduana, distribución, última milla o importación detenida solo si aparecen en los datos.\n- No inventes fechas, países, estados ni tiempos de entrega.",
        ];

        foreach ($prompts as $slug => $content) {
            AiPromptSection::updateOrCreate(
                ['slug' => $slug],
                ['name' => match ($slug) { 'cotizaciones_ia' => 'Cotizaciones IA', 'seguimiento_nacional_ia' => 'Seguimiento nacional IA', default => 'Seguimiento internacional IA' }, 'content' => $content, 'active' => true],
            );
        }
    }

    public function down(): void
    {
        DB::table('ai_prompt_sections')->where('slug', 'general_system')->delete();
    }
};
