<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('postal_campaign_templates')
            ->where(function ($query): void {
                $query->where('scope', 'nacional')->where('category', 'cancelled');
            })
            ->orWhere(function ($query): void {
                $query->where('scope', 'internacional')->where('category', 'import_stopped');
            })
            ->delete();
    }

    public function down(): void
    {
    }
};
