<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('postal_packages', function (Blueprint $table): void {
            $table->index(['scope', 'source', 'updated_at_api'], 'postal_packages_campaign_scope_source_updated_idx');
            $table->index(['scope', 'source', 'current_event'], 'postal_packages_campaign_scope_source_event_idx');
        });
    }

    public function down(): void
    {
        Schema::table('postal_packages', function (Blueprint $table): void {
            $table->dropIndex('postal_packages_campaign_scope_source_updated_idx');
            $table->dropIndex('postal_packages_campaign_scope_source_event_idx');
        });
    }
};
