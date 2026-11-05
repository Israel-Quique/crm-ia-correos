<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postal_campaign_messages', function (Blueprint $table): void {
            $table->text('message')->change();
        });
    }

    public function down(): void
    {
        Schema::table('postal_campaign_messages', function (Blueprint $table): void {
            $table->string('message', 255)->change();
        });
    }
};
