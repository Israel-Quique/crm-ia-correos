<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('automatic_responses', function (Blueprint $table) { $table->json('trigger_phrases')->nullable()->after('template'); }); } public function down(): void { Schema::table('automatic_responses', function (Blueprint $table) { $table->dropColumn('trigger_phrases'); }); } };
