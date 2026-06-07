<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\UserKycSubmitted;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\StateMachines\KycStateMachine;
use App\Shared\Enums\KycStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class SubmitKycAction
{
    private const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024;

    /**
     * @var string[]
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    public function __construct(private readonly KycStateMachine $kyc) {}

    /**
     * Submit KYC documents (NOT_SUBMITTED -> SUBMITTED).
     *
     * @param  array<int, mixed>  $documents
     */
    public function execute(User $user, string $documentType, array $documents): KycSubmission
    {
        return DB::transaction(function () use ($user, $documentType, $documents): KycSubmission {
            if ($documents === []) {
                throw new InvalidArgumentException('At least one KYC document is required.');
            }

            $latestSubmission = (new KycSubmission)
                ->newQuery()
                ->where('user_id', $user->getKey())
                ->latest('id')
                ->first();

            if ($latestSubmission instanceof KycSubmission) {
                $latestStatus = $latestSubmission->getAttribute('status');

                if (in_array($latestStatus, [KycStatus::SUBMITTED, KycStatus::UNDER_REVIEW, KycStatus::APPROVED], true)) {
                    throw new LogicException('User already has an active KYC submission.');
                }
            }

            $paths = [];

            foreach ($documents as $document) {
                if (! $document instanceof UploadedFile) {
                    throw new InvalidArgumentException('KYC documents must be uploaded files.');
                }

                if (! $document->isValid()) {
                    throw new InvalidArgumentException('KYC document upload is invalid.');
                }

                if ($document->getSize() > self::MAX_DOCUMENT_BYTES) {
                    throw new InvalidArgumentException('KYC document exceeds the maximum allowed size.');
                }

                if (! in_array($document->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
                    throw new InvalidArgumentException('KYC document type is not allowed.');
                }

                $path = $document->store('kyc/'.((int) $user->getKey()), 'local');

                if ($path === false) {
                    throw new LogicException('Unable to store KYC document.');
                }

                $paths[] = $path;
            }

            $submission = $latestSubmission instanceof KycSubmission
                && $latestSubmission->getAttribute('status') === KycStatus::REJECTED
                    ? $latestSubmission
                    : new KycSubmission;

            if (! $submission->exists) {
                $submission->fill([
                    'uuid' => Str::uuid()->toString(),
                    'user_id' => $user->getKey(),
                    'status' => KycStatus::NOT_SUBMITTED,
                ]);
            }

            $submission->fill([
                'document_type' => $documentType,
                'document_paths' => $paths,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ]);
            $submission->save();

            if ($submission->getAttribute('status') !== KycStatus::SUBMITTED) {
                $this->kyc->transition($submission, KycStatus::SUBMITTED);
            }

            UserKycSubmitted::dispatch((int) $user->getKey(), (int) $submission->getKey());

            return $submission;
        });
    }
}
