<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('whatsapp_messages', function (Blueprint $table): void { $table->string('source', 40)->default('whatsapp')->index(); $table->unsignedBigInteger('source_id')->nullable()->index(); $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->string('language_code', 10)->nullable(); }); }
    public function down(): void { Schema::table('whatsapp_messages', function (Blueprint $table): void { $table->dropForeign(['sender_user_id']); $table->dropColumn(['source', 'source_id', 'sender_user_id', 'language_code']); }); }
};
