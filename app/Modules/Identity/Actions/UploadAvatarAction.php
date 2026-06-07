<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use Illuminate\Http\UploadedFile;

class UploadAvatarAction
{
    /**
     * Store an avatar image via spatie/laravel-medialibrary.
     */
    public function execute(User $user, UploadedFile $file): void
    {
        $user->clearMediaCollection('avatar');

        $user->addMedia($file)
            ->toMediaCollection('avatar');
    }
}
