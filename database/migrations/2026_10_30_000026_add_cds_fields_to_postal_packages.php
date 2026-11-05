<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('postal_packages', function (Blueprint $table) { $table->string('cds_sender')->nullable()->after('sender'); $table->string('cds_recipient')->nullable()->after('recipient'); $table->string('cds_phone')->nullable()->after('phone'); $table->timestamp('cds_synced_at')->nullable()->index(); }); }
    public function down(): void { Schema::table('postal_packages', function (Blueprint $table) { $table->dropColumn(['cds_sender','cds_recipient','cds_phone','cds_synced_at']); }); }
};
