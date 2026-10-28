<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_records', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('external_id');
            $table->string('status')->nullable()->index();
            $table->jsonb('payload');
            $table->timestamp('api_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['source', 'external_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('api_records'); }
};
