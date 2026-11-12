<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->timestamps();
        });
        Schema::create('gateway_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_gateway_id')->constrained()->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('legacy')->default(false);
            $table->timestamps();
        });
        Schema::create('gateway_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_configuration_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('value');
            $table->boolean('uses_environment')->default(false);
            $table->timestamps();
            $table->unique(['gateway_configuration_id', 'name']);
        });
        foreach (['payment_checkouts', 'withdrawals'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->foreignId('gateway_configuration_id')->nullable()->constrained()->restrictOnDelete());
        }
        DB::transaction(function () {
            DB::table('system_settings')->where('key', 'feature.kyc_required')->delete();
            foreach (['stripe' => ['secret', 'webhook_secret'], 'payop' => ['public_key', 'secret', 'payout_token', 'payout_certificate']] as $provider => $names) {
                $gateway = DB::table('payment_gateways')->insertGetId(['provider' => $provider, 'created_at' => now(), 'updated_at' => now()]);
                $lastEditor = DB::table('system_settings')->where('key', 'like', 'payments.'.$provider.'.%')->latest('updated_at')->value('updated_by');
                $configuration = null;
                foreach ($names as $name) {
                    $key = 'payments.'.$provider.'.'.$name;
                    $row = DB::table('system_settings')->where('key', $key)->first();
                    $value = $row?->value ?? Crypt::encryptString((string) config('services.'.$provider.'.'.$name, ''));
                    // Keep a frozen copy for payments created before this migration.
                    $configuration ??= DB::table('gateway_configurations')->insertGetId(['payment_gateway_id' => $gateway, 'updated_by' => $lastEditor, 'legacy' => true, 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('gateway_credentials')->insert(['gateway_configuration_id' => $configuration, 'name' => $name, 'value' => $value, 'uses_environment' => $row === null || $row->value === null, 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('system_settings')->where('key', $key)->delete();
                }
                if ($provider === 'payop') {
                    DB::table('payment_checkouts')->where('provider', 'payop')->update(['gateway_configuration_id' => $configuration]);
                    DB::table('withdrawals')->where('payout_provider', 'payop')->update(['gateway_configuration_id' => $configuration]);
                }
            }
        });
    }

    public function down(): void
    {
        $latest = DB::table('gateway_configurations')->selectRaw('MAX(id) AS id')->groupBy('payment_gateway_id')->pluck('id');
        $pendingCheckout = DB::table('payment_checkouts')->whereNotNull('gateway_configuration_id')->whereNotIn('gateway_configuration_id', $latest)->whereNull('completed_at')->exists();
        $pendingPayout = DB::table('withdrawals')->whereNotNull('gateway_configuration_id')->whereNotIn('gateway_configuration_id', $latest)->whereIn('status', ['pending', 'under_review', 'approved'])->exists();
        if ($pendingCheckout || $pendingPayout) {
            throw new LogicException('Reconcile payments using earlier credentials before rolling back gateway storage.');
        }
        foreach (DB::table('payment_gateways')->get() as $gateway) {
            $configuration = DB::table('gateway_configurations')->where('payment_gateway_id', $gateway->id)->max('id');
            foreach (DB::table('gateway_credentials')->where('gateway_configuration_id', $configuration)->where('uses_environment', false)->get() as $credential) {
                DB::table('system_settings')->updateOrInsert(['key' => 'payments.'.$gateway->provider.'.'.$credential->name], ['value' => $credential->value, 'updated_at' => now(), 'created_at' => now()]);
            }
        }
        foreach (['payment_checkouts', 'withdrawals'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropConstrainedForeignId('gateway_configuration_id'));
        }
        Schema::dropIfExists('gateway_credentials');
        Schema::dropIfExists('gateway_configurations');
        Schema::dropIfExists('payment_gateways');
    }
};
