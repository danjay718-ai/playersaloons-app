<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('accepted_terms_at')->nullable()->after('last_login_at');
            $table->timestamp('accepted_privacy_policy_at')->nullable()->after('accepted_terms_at');
            $table->timestamp('accepted_cookie_policy_at')->nullable()->after('accepted_privacy_policy_at');
            $table->timestamp('age_confirmed_at')->nullable()->after('accepted_cookie_policy_at');
            $table->boolean('newsletter_subscribed')->default(false)->after('age_confirmed_at');
            $table->timestamp('newsletter_subscribed_at')->nullable()->after('newsletter_subscribed');
            $table->string('policy_acceptance_ip', 45)->nullable()->after('newsletter_subscribed_at');
            $table->text('policy_acceptance_user_agent')->nullable()->after('policy_acceptance_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'accepted_terms_at',
                'accepted_privacy_policy_at',
                'accepted_cookie_policy_at',
                'age_confirmed_at',
                'newsletter_subscribed',
                'newsletter_subscribed_at',
                'policy_acceptance_ip',
                'policy_acceptance_user_agent',
            ]);
        });
    }
};
