<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $subject
 * @property string $content
 * @property string $status
 * @property int $recipient_count
 * @property int $sent_count
 * @property int $failed_count
 * @property int $created_by
 * @property Carbon|null $sent_at
 * @property-read User $creator
 */
class NewsletterCampaign extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'subject',
        'content',
        'status',
        'recipient_count',
        'sent_count',
        'failed_count',
        'created_by',
        'sent_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
