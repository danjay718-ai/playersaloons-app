<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Enums\KycStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property KycStatus $status
 */
class KycSubmission extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'status',
        'document_type',
        'document_paths',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KycStatus::class,
            'document_paths' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Legacy/admin display compatibility: KYC uploads are stored in document_paths.
     *
     * @return Attribute<string|null, never>
     */
    protected function documentFrontPath(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->documentPathAt(0));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function documentBackPath(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->documentPathAt(1));
    }

    private function documentPathAt(int $index): ?string
    {
        $paths = $this->getAttribute('document_paths');

        if (! is_array($paths)) {
            return null;
        }

        $path = $paths[$index] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * Get the user that owns the KYC submission.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin/reviewer who reviewed the KYC submission.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
