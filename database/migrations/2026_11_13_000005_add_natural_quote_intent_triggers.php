<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const TRIGGER_PHRASES = [
        'quisiera cotizar el envio de un paquete',
        'quisiera cotizar el envio',
        'quisiera cotizar envio de paquete',
        'quisiera cotizar un paquete',
        'quiero cotizar el envio de un paquete',
        'quiero cotizar el envio',
        'quiero cotizar un paquete',
        'cotizar el envio de un paquete',
        'cotizar el envio',
        'cotizar envio de un paquete',
        'necesito cotizar el envio',
        'necesito cotizar un paquete',
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
