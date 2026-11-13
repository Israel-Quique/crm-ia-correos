<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const TRIGGER_PHRASES = [
        'quisiera mandar un paquete',
        'quiero mandar un paquete',
        'mandar un paquete',
        'mandar paquete',
        'quisiera enviar un paquete',
        'quiero enviar un paquete',
        'necesito mandar un paquete',
        'necesito enviar un paquete',
        'enviar un paquete',
        'enviar paquete',
        'quiero hacer un envio',
        'quisiera hacer un envio',
        'quiero despachar un paquete',
        'despachar un paquete',
    ];

    public function up(): void
    {
        $this->updateTriggers(
            fn (array $triggers): array => array_values(array_unique(array_merge($triggers, self::TRIGGER_PHRASES))),
        );
    }

    public function down(): void
    {
        $this->updateTriggers(
            fn (array $triggers): array => array_values(array_diff($triggers, self::TRIGGER_PHRASES)),
        );
    }

    private function updateTriggers(callable $transform): void
    {
        $response = DB::table('automatic_responses')
            ->where('scope', 'general')
            ->where('type', 'quote')
            ->where('key', 'cotizacion_postal')
            ->whereNull('user_id')
            ->first(['id', 'trigger_phrases']);

        if ($response === null) {
            return;
        }

        $triggers = json_decode((string) $response->trigger_phrases, true);
        $triggers = is_array($triggers) ? $triggers : [];

        DB::table('automatic_responses')
            ->where('id', $response->id)
            ->update([
                'trigger_phrases' => json_encode($transform($triggers), JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }
};
