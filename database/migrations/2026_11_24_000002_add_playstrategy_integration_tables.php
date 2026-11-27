<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            if (! Schema::hasColumn('games', 'gameplay_provider')) {
                $table->string('gameplay_provider', 40)->default('manual')->after('game_category_id');
                $table->index('gameplay_provider');
            }
            if (! Schema::hasColumn('games', 'provider_config')) {
                $table->json('provider_config')->nullable()->after('gameplay_provider');
            }
        });

        if (! Schema::hasTable('external_provider_accounts')) {
            Schema::create('external_provider_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('provider', 40);
                $table->string('external_user_id', 120);
                $table->string('external_username', 120);
                $table->text('access_token');
                $table->text('refresh_token')->nullable();
                $table->string('scopes', 255)->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->timestamp('verified_at');
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'provider']);
                $table->unique(['provider', 'external_user_id']);
                $table->index(['provider', 'external_username']);
            });
        }

        if (! Schema::hasTable('external_match_games')) {
            Schema::create('external_match_games', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
                $table->foreignId('match_attempt_id')->nullable()->constrained('match_attempts')->nullOnDelete();
                $table->unsignedInteger('attempt_number')->default(1);
                $table->string('provider', 40);
                $table->string('provider_variant', 80);
                $table->json('expected_settings_json')->nullable();
                $table->string('player_a_external_user_id', 120)->nullable();
                $table->string('player_b_external_user_id', 120)->nullable();
                $table->string('external_game_id', 120)->nullable();
                $table->string('play_url', 500)->nullable();
                $table->string('status', 40)->default('pending');
                $table->string('provider_status', 60)->nullable();
                $table->string('provider_winner_side', 20)->nullable();
                $table->timestamp('creation_started_at')->nullable();
                $table->timestamp('last_polled_at')->nullable();
                $table->timestamp('next_poll_at')->nullable();
                $table->unsignedInteger('poll_attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamps();

                $table->unique(['match_id', 'attempt_number']);
                $table->unique(['provider', 'external_game_id']);
                $table->index(['provider', 'status', 'next_poll_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('external_match_games');
        Schema::dropIfExists('external_provider_accounts');

        Schema::table('games', function (Blueprint $table): void {
            if (Schema::hasColumn('games', 'provider_config')) {
                $table->dropColumn('provider_config');
            }
            if (Schema::hasColumn('games', 'gameplay_provider')) {
                $table->dropIndex(['gameplay_provider']);
                $table->dropColumn('gameplay_provider');
            }
        });
    }
};
