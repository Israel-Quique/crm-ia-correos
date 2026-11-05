<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->index();
        });

        DB::table('whatsapp_messages')
            ->where('direction', 'inbound')
            ->whereNull('read_at')
            ->update(['read_at' => DB::raw('COALESCE(received_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['read_at']);
            $table->dropColumn('read_at');
        });
    }
};
