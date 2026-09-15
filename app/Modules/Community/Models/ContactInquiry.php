<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property string $category
 * @property string $subject
 * @property string $message
 * @property string $status
 * @property string|null $admin_notes
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property-read User|null $user
 * @property-read User|null $resolver
 */
class ContactInquiry extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'email',
        'category',
        'subject',
        'message',
        'status',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withTrashed();
    }
}
