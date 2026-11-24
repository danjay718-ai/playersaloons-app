<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('game_categories')) {
            Schema::create('game_categories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name', 120);
                $table->string('slug', 120)->unique();
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'display_order']);
            });
        }

        $now = now();
        $initialCategories = [
            ['name' => 'Video Games', 'slug' => 'video-games', 'display_order' => 1],
            ['name' => 'Card & Board Games', 'slug' => 'card-board-games', 'display_order' => 2],
        ];

        foreach ($initialCategories as $category) {
            $exists = DB::table('game_categories')->where('slug', $category['slug'])->exists();
            if (! $exists) {
                DB::table('game_categories')->insert([
                    'uuid' => Str::uuid()->toString(),
                    'name' => $category['name'],
                    'slug' => $category['slug'],
                    'display_order' => $category['display_order'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $videoGamesCategoryId = DB::table('game_categories')
            ->where('slug', 'video-games')
            ->value('id');

        if (! Schema::hasColumn('games', 'game_category_id')) {
            Schema::table('games', function (Blueprint $table): void {
                $table->unsignedBigInteger('game_category_id')->nullable()->after('slug');
            });
        }

        // Backfill every existing game, including soft-deleted rows, to Video Games.
        DB::table('games')
            ->whereNull('game_category_id')
            ->update(['game_category_id' => $videoGamesCategoryId]);

        Schema::table('games', function (Blueprint $table): void {
            $table->unsignedBigInteger('game_category_id')->nullable(false)->change();
            $table->foreign('game_category_id')
                ->references('id')
                ->on('game_categories')
                ->restrictOnDelete();
            $table->index(['game_category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('games', 'game_category_id')) {
            Schema::table('games', function (Blueprint $table): void {
                $table->dropForeign(['game_category_id']);
                $table->dropIndex(['game_category_id', 'is_active']);
                $table->dropColumn('game_category_id');
            });
        }

        Schema::dropIfExists('game_categories');
    }
};
