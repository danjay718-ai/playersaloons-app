<?php

declare(strict_types=1);

namespace App\Modules\Operations\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorIncident extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'reference_id',
        'fingerprint',
        'level',
        'source',
        'status_code',
        'exception_class',
        'message',
        'route',
        'method',
        'path',
        'user_id',
        'ip_hash',
        'context',
        'stack_trace',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withTrashed();
    }
}
