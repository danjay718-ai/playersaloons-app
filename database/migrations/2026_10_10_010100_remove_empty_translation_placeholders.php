<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only unused placeholder rows are removed; actual translations remain.
        DB::table('translation_strings')->where('locale', '!=', 'en')
            ->whereNull('deleted_at')->where(fn ($query) => $query->whereNull('text')->orWhere('text', ''))
            ->delete();
    }

    public function down(): void
    {
        // Placeholder rows held no translations and are no longer needed.
    }
};
