<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class KycDocumentController extends Controller
{
    public function __invoke(string $path): Response
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'KYC_REVIEWER'])) {
            abort(403, 'Unauthorized access to KYC document.');
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            abort(404, 'KYC document not found.');
        }

        return $disk->response($path);
    }
}
