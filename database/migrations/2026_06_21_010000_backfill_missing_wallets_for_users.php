<?php

use App\Shared\Enums\WalletStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('users')
            ->leftJoin('wallets', 'users.id', '=', 'wallets.user_id')
            ->whereNull('wallets.id')
            ->select('users.id')
            ->orderBy('users.id')
            ->chunkById(500, function ($users) use ($now): void {
                $wallets = [];

                foreach ($users as $user) {
                    $wallets[] = [
                        'uuid' => Str::uuid()->toString(),
                        'user_id' => $user->id,
                        'cached_balance' => '0.00',
                        'status' => WalletStatus::ACTIVE->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($wallets !== []) {
                    DB::table('wallets')->insertOrIgnore($wallets);
                }
            }, 'users.id', 'id');
    }

    public function down(): void
    {
        // Intentionally no-op: deleting wallets on rollback would destroy financial records.
    }
};
