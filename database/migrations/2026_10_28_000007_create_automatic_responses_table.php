<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('automatic_responses', function (Blueprint $table) { $table->id(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('type'); $table->string('key'); $table->string('name'); $table->text('template'); $table->boolean('active')->default(true); $table->timestamps(); $table->index(['type','key','active']); }); } public function down(): void { Schema::dropIfExists('automatic_responses'); } };
