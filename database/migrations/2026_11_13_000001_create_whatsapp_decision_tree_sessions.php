<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_decision_tree_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 32)->unique();
            $table->string('flow_key', 32);
            $table->string('step', 48);
            $table->json('context')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('app_settings')->insertOrIgnore([
            ['key' => 'automatic_decision_flow_quote', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'automatic_decision_flow_tracking', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('app_settings')
            ->whereIn('key', ['automatic_decision_flow_quote', 'automatic_decision_flow_tracking'])
            ->delete();

        Schema::dropIfExists('whatsapp_decision_tree_sessions');
    }
};
