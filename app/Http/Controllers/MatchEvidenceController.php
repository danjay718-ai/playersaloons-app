<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\MatchEvidence;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MatchEvidenceController extends Controller
{
    public function __invoke(MatchEvidence $evidence): Response
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            abort(403, 'Unauthorized access to dispute evidence.');
        }

        $evidence->loadMissing('dispute.match.playerARegistration', 'dispute.match.playerBRegistration');
        $match = $evidence->dispute->match;

        $isAuthorizedStaff = $user->can('disputes.view')
            || $user->can('matches.manage')
            || $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'SUPPORT_AGENT', 'TOURNAMENT_ORGANIZER']);

        $isParticipant = $match->playerARegistration?->includesUser((int) $user->getKey()) === true
            || $match->playerBRegistration?->includesUser((int) $user->getKey()) === true;

        if (! $isAuthorizedStaff && ! $isParticipant) {
            abort(403, 'Unauthorized access to dispute evidence.');
        }

        $path = $evidence->file_path;
        if (! is_string($path) || $path === '') {
            abort(404, 'Evidence file not found.');
        }

        // Check private disk first; fall back to legacy public disk for pre-migration uploads.
        foreach (['local', 'public'] as $diskName) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk($diskName);
            if ($disk->exists($path)) {
                return $disk->response($path);
            }
        }

        abort(404, 'Evidence file not found.');
    }
}
