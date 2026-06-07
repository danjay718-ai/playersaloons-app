<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\Models\MatchEvidence;
use App\Shared\Enums\DisputeStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class SubmitEvidenceAction
{
    private const MAX_FILE_BYTES = 20 * 1024 * 1024; // 20 MB

    /**
     * @var string[]
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
        'video/quicktime',
    ];

    /**
     * Submit evidence for a match dispute.
     */
    public function execute(MatchDispute $dispute, int $uploadedByUserId, UploadedFile $file): MatchEvidence
    {
        return DB::transaction(function () use ($dispute, $uploadedByUserId, $file): MatchEvidence {
            // Validate dispute status
            if ($dispute->status === DisputeStatus::RESOLVED) {
                throw new LogicException('Cannot submit evidence to a resolved dispute.');
            }

            // Validate submitter is a participant
            $playerAUserId = $dispute->match->playerARegistration?->user_id;
            $playerBUserId = $dispute->match->playerBRegistration?->user_id;

            if ($uploadedByUserId !== $playerAUserId && $uploadedByUserId !== $playerBUserId) {
                throw new InvalidArgumentException('Only match participants can submit evidence.');
            }

            // Validate file upload
            if (! $file->isValid()) {
                throw new InvalidArgumentException('Uploaded file is invalid.');
            }

            if ($file->getSize() > self::MAX_FILE_BYTES) {
                throw new InvalidArgumentException('Evidence file size exceeds the 20MB limit.');
            }

            if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
                throw new InvalidArgumentException('Invalid file type. Only PDFs, images, and MP4/MOV videos are allowed.');
            }

            // Store file in R2 disk
            $path = $file->store("disputes/{$dispute->id}/evidence", 'r2');
            if ($path === false) {
                throw new LogicException('Failed to store evidence file.');
            }

            // Update dispute status to under_review if it was open
            if ($dispute->status === DisputeStatus::OPEN) {
                $dispute->status = DisputeStatus::UNDER_REVIEW;
                $dispute->save();
            }

            // Create MatchEvidence record
            return MatchEvidence::query()->create([
                'uuid' => Str::uuid()->toString(),
                'dispute_id' => $dispute->id,
                'uploaded_by' => $uploadedByUserId,
                'file_path' => $path,
                'created_at' => Carbon::now(),
            ]);
        });
    }
}
