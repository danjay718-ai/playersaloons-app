<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class UploadAvatarAction
{
    private const MAX_FILE_BYTES = 2 * 1024 * 1024; // 2 MB

    /** @var string[] */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Store an avatar on the public disk and update the user's profile avatar_url.
     * Uses the same approach as dispute evidence uploads (local public disk + DB path)
     * — no S3/MinIO driver required.
     */
    public function execute(User $user, UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Uploaded avatar file is invalid.');
        }

        if ($file->getSize() > self::MAX_FILE_BYTES) {
            throw new InvalidArgumentException('Avatar file size exceeds the 2MB limit.');
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Invalid file type. Only JPEG, PNG, and WebP images are allowed.');
        }

        // Ensure profile row exists (same guard as UpdateProfileAction)
        /** @var UserProfile $profile */
        $profile = $user->profile()->firstOrCreate(
            ['user_id' => $user->getKey()],
            [
                'uuid'         => Str::uuid()->toString(),
                'display_name' => (string) $user->getAttribute('username'),
            ]
        );

        // Delete the old avatar file from disk if it was stored locally
        if ($profile->avatar_url) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url($profile->avatar_url, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Store on the local public disk (same as dispute evidence)
        $path = $file->store("avatars/{$user->getKey()}", 'public');

        if ($path === false) {
            throw new LogicException('Failed to store avatar file.');
        }

        $profile->avatar_url = Storage::disk('public')->url($path);
        $profile->save();
    }
}
