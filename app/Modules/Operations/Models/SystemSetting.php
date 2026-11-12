<?php

namespace App\Modules\Operations\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Operations\Services\SystemSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];

    protected static function booted(): void
    {
        $forget = fn () => app(SystemSettings::class)->forget();
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Get the user who last updated this setting.
     *
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
