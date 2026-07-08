<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stream_channels', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->unsignedBigInteger('viewer_count')->default(0)->after('description');
            $table->unsignedBigInteger('total_views')->default(0)->after('viewer_count');
            $table->boolean('is_featured')->default(false)->after('is_public');
            $table->boolean('is_live')->default(false)->after('is_featured');
            $table->string('thumbnail_url')->nullable()->after('is_live');

            $table->index(['is_live', 'is_public', 'taken_down_at']);
            $table->index(['is_featured', 'is_public', 'taken_down_at']);
        });
    }

    public function down(): void
    {
        Schema::table('stream_channels', function (Blueprint $table) {
            $table->dropIndex(['is_live', 'is_public', 'taken_down_at']);
            $table->dropIndex(['is_featured', 'is_public', 'taken_down_at']);
            $table->dropColumn(['description', 'viewer_count', 'total_views', 'is_featured', 'is_live', 'thumbnail_url']);
        });
    }
};
