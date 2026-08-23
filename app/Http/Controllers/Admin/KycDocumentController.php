<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;


class KycDocumentController extends Controller
{
    public function __invoke(string $path): Response
    {
        /** @var \App\Modules\Identity\Models\User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'KYC_REVIEWER'])) {
            abort(403, 'Unauthorized access to KYC document.');
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            abort(404, 'KYC document not found.');
        }

        return $disk->response($path);
    }
}
