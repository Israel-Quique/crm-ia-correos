<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug')->unique();
            $table->text('description')->nullable(); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('modules', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id(); $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('name'); $table->string('slug'); $table->string('action'); $table->timestamps();
            $table->unique(['module_id', 'slug']);
        });
        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user'); Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions'); Schema::dropIfExists('modules'); Schema::dropIfExists('roles');
    }
};
