<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id(); $table->string('provider_message_id')->nullable()->unique(); $table->string('phone')->index(); $table->string('contact_name')->nullable(); $table->string('direction')->default('inbound')->index(); $table->string('message_type')->default('text'); $table->text('message')->nullable(); $table->string('status')->nullable()->index(); $table->json('raw_payload')->nullable(); $table->timestamp('received_at')->nullable()->index(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('whatsapp_messages'); }
};
