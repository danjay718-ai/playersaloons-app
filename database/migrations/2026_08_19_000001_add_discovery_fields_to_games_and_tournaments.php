<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->string('card_image_path')->nullable()->after('banner_path');
        });

        Schema::table('tournaments', function (Blueprint $table): void {
            $table->boolean('is_featured')->default(false)->after('banner_url')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table): void {
            $table->dropIndex(['is_featured']);
            $table->dropColumn('is_featured');
        });

        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn('card_image_path');
        });
    }
};
