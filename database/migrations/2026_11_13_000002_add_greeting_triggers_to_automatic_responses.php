<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const TRIGGERS = [
        'hola',
        'buenas',
        'buenos dias',
        'buenas tardes',
        'buenas noches',
        'saludos',
    ];

    public function up(): void
    {
        $this->updateGreetingTriggers(function (array $triggers): array {
            return array_values(array_unique(array_merge($triggers, self::TRIGGERS)));
        });
    }

    public function down(): void
    {
        $this->updateGreetingTriggers(
            fn (array $triggers): array => array_values(array_diff($triggers, self::TRIGGERS)),
        );
    }

    private function updateGreetingTriggers(callable $transform): void
    {
        $greeting = DB::table('automatic_responses')
            ->where('scope', 'general')
            ->where('type', 'event')
            ->where('key', 'saludo')
            ->whereNull('user_id')
            ->first(['id', 'trigger_phrases']);

        if ($greeting === null) {
            return;
        }

        $triggers = json_decode((string) $greeting->trigger_phrases, true);
        $triggers = is_array($triggers) ? $triggers : [];

        DB::table('automatic_responses')
            ->where('id', $greeting->id)
            ->update([
                'trigger_phrases' => json_encode($transform($triggers), JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }
};
