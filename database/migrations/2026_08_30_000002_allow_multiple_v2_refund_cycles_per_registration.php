<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL may use the old composite unique key to support either FK.
        // Each index is checked independently because a failed prior migration
        // can leave its first ALTER TABLE statement committed.
        $this->addIndexIfMissing('tournament_id', 'refund_tournament_id_index');
        $this->addIndexIfMissing('registration_id', 'refund_registration_id_index');

        if ($this->indexExists('refund_tournament_registration_unique')) {
            Schema::table('refunds', function (Blueprint $table): void {
                $table->dropUnique('refund_tournament_registration_unique');
            });
        }
    }

    public function down(): void
    {
        // Historical repeat cancellation/refund cycles may already exist, so
        // restoring this uniqueness rule is intentionally unsafe.
    }

    private function addIndexIfMissing(string $column, string $name): void
    {
        if ($this->indexExists($name)) {
            return;
        }

        Schema::table('refunds', function (Blueprint $table) use ($column, $name): void {
            $table->index($column, $name);
        });
    }

    private function indexExists(string $name): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'refunds')
            ->where('index_name', $name)
            ->exists();
    }
};
