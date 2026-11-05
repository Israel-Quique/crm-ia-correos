<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postal_campaign_templates', function (Blueprint $table): void {
            $table->string('scope', 20)->default('nacional')->after('id');
        });

        Schema::table('postal_campaign_templates', function (Blueprint $table): void {
            $table->dropUnique('postal_campaign_templates_name_unique');
        });

        $nationalTemplates = DB::table('postal_campaign_templates')->get();
        foreach ($nationalTemplates as $template) {
            DB::table('postal_campaign_templates')->insert([
                'name' => $template->name,
                'template' => $template->template,
                'scope' => 'internacional',
                'user_id' => $template->user_id,
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
            ]);
        }

        Schema::table('postal_campaign_templates', function (Blueprint $table): void {
            $table->unique(['scope', 'name']);
        });
    }

    public function down(): void
    {
        DB::table('postal_campaign_templates')->where('scope', 'internacional')->delete();

        Schema::table('postal_campaign_templates', function (Blueprint $table): void {
            $table->dropUnique('postal_campaign_templates_scope_name_unique');
            $table->dropColumn('scope');
            $table->unique('name');
        });
    }
};
