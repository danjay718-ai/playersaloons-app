<?php

declare(strict_types=1);

namespace App\Modules\Operations\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class SystemSettings
{
    private ?array $values = null;

    public function all(): array
    {
        return $this->values ??= SystemSetting::query()->pluck('value', 'key')->all();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function forget(): void
    {
        $this->values = null;
    }

    public function save(User $actor, array $values): void
    {
        if (! $actor->can('system_settings.manage')) {
            throw new AuthorizationException;
        }
        DB::transaction(function () use ($actor, $values): void {
            $before = SystemSetting::query()->whereIn('key', array_keys($values))->lockForUpdate()->pluck('value', 'key')->all();
            foreach ($values as $key => $value) {
                SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value, 'updated_by' => $actor->id]);
            }
            activity()->causedBy($actor)->withProperties(['before' => $before, 'after' => $values])->log('system_settings_updated');
        });
        $this->forget();
    }
}
