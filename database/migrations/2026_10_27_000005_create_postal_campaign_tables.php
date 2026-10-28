<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('postal_packages', function (Blueprint $table) {
            $table->id(); $table->string('source'); $table->string('external_id');
            $table->string('tracking_number')->index(); $table->string('sender')->nullable(); $table->string('recipient')->nullable();
            $table->string('phone')->nullable()->index(); $table->string('current_event')->nullable()->index(); $table->string('destination_branch')->nullable();
            $table->timestamp('updated_at_api')->nullable()->index(); $table->jsonb('raw_payload')->nullable(); $table->timestamp('last_synced_at')->nullable(); $table->timestamps();
            $table->unique(['source', 'external_id']);
        });
        Schema::create('postal_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('postal_package_id')->constrained()->cascadeOnDelete(); $table->string('source_event'); $table->string('event_name'); $table->timestamp('event_at')->nullable(); $table->jsonb('raw_payload')->nullable(); $table->timestamps(); $table->index(['postal_package_id', 'event_at']);
        });
        Schema::create('postal_campaigns', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->jsonb('filters'); $table->jsonb('events')->nullable(); $table->text('template'); $table->unsignedInteger('interval_seconds')->default(5); $table->boolean('test_mode')->default(true); $table->string('status')->default('draft'); $table->unsignedInteger('total')->default(0); $table->unsignedInteger('sent')->default(0); $table->unsignedInteger('failed')->default(0); $table->unsignedInteger('pending')->default(0); $table->timestamps();
        });
        Schema::create('postal_campaign_messages', function (Blueprint $table) {
            $table->id(); $table->foreignId('postal_campaign_id')->constrained()->cascadeOnDelete(); $table->foreignId('postal_package_id')->constrained()->cascadeOnDelete(); $table->string('phone')->nullable(); $table->string('message'); $table->string('status')->default('pending')->index(); $table->string('provider_message_id')->nullable(); $table->text('error_message')->nullable(); $table->timestamp('sent_at')->nullable(); $table->timestamps(); $table->unique(['postal_campaign_id', 'postal_package_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('postal_campaign_messages'); Schema::dropIfExists('postal_campaigns'); Schema::dropIfExists('postal_events'); Schema::dropIfExists('postal_packages'); }
};
