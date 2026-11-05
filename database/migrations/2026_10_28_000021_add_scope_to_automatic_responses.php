<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('automatic_responses', function (Blueprint $table) { $table->string('scope')->default('general')->after('user_id'); $table->index(['scope','type','key','active']); }); }
    public function down(): void { Schema::table('automatic_responses', function (Blueprint $table) { $table->dropIndex('automatic_responses_scope_type_key_active_index'); $table->dropColumn('scope'); }); }
};
