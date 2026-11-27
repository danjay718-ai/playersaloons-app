<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_messages', function (Blueprint $table): void {
            $table->string('display_type', 20)->default('feed')->after('message');
            $table->string('target_surface', 24)->default('both')->after('display_type');
            $table->string('badge_label', 60)->nullable()->after('target_surface');
            $table->string('media_type', 16)->default('none')->after('badge_label');
            $table->string('media_url', 1000)->nullable()->after('media_type');
            $table->string('cta_label', 80)->nullable()->after('media_url');
            $table->string('cta_url', 1000)->nullable()->after('cta_label');
            $table->boolean('is_video_only')->default(false)->after('cta_url');

            $table->index(['display_type', 'target_surface', 'starts_at', 'ends_at'], 'broadcast_messages_popup_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_messages', function (Blueprint $table): void {
            $table->dropIndex('broadcast_messages_popup_lookup_idx');
            $table->dropColumn([
                'display_type',
                'target_surface',
                'badge_label',
                'media_type',
                'media_url',
                'cta_label',
                'cta_url',
                'is_video_only',
            ]);
        });
    }
};
