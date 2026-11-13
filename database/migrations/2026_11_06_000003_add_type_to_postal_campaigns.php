<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('postal_campaigns', fn (Blueprint $table) => $table->string('campaign_type')->default('operativa')->after('name')->index()); } public function down(): void { Schema::table('postal_campaigns', fn (Blueprint $table) => $table->dropColumn('campaign_type')); } };
