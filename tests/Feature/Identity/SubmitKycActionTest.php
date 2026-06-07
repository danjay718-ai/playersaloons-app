<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Actions\SubmitKycAction;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\KycStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class SubmitKycActionTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        $user = new User;
        $user->fill([
            'uuid' => fake()->uuid(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'status' => 'active',
        ]);
        $user->save();

        return $user;
    }

    public function test_it_submits_and_stores_valid_kyc_documents(): void
    {
        Storage::fake('local');

        $user = $this->createUser();
        $submission = app(SubmitKycAction::class)->execute($user, 'passport', [
            UploadedFile::fake()->create('passport.pdf', 64, 'application/pdf'),
        ]);

        $this->assertSame(KycStatus::SUBMITTED, $submission->getAttribute('status'));
        $this->assertSame('passport', $submission->getAttribute('document_type'));

        $paths = $submission->getAttribute('document_paths');
        $this->assertIsArray($paths);
        $this->assertCount(1, $paths);

        $this->assertTrue(Storage::disk('local')->exists($paths[0]));
    }

    public function test_it_blocks_duplicate_active_kyc_submissions(): void
    {
        Storage::fake('local');

        $user = $this->createUser();

        KycSubmission::query()->create([
            'uuid' => fake()->uuid(),
            'user_id' => $user->getKey(),
            'status' => KycStatus::SUBMITTED,
            'document_type' => 'passport',
            'document_paths' => ['kyc/existing.jpg'],
        ]);

        $this->expectException(LogicException::class);

        app(SubmitKycAction::class)->execute($user, 'passport', [
            UploadedFile::fake()->create('passport.pdf', 64, 'application/pdf'),
        ]);
    }

    public function test_it_resubmits_latest_rejected_kyc_submission(): void
    {
        Storage::fake('local');

        $user = $this->createUser();

        $rejected = KycSubmission::query()->create([
            'uuid' => fake()->uuid(),
            'user_id' => $user->getKey(),
            'status' => KycStatus::REJECTED,
            'document_type' => 'passport',
            'document_paths' => ['kyc/old.jpg'],
            'review_notes' => 'blurry',
        ]);

        $submission = app(SubmitKycAction::class)->execute($user, 'national_id', [
            UploadedFile::fake()->create('national-id.png', 64, 'image/png'),
        ]);

        $this->assertSame($rejected->getKey(), $submission->getKey());
        $this->assertSame(KycStatus::SUBMITTED, $submission->getAttribute('status'));
        $this->assertSame('national_id', $submission->getAttribute('document_type'));
        $this->assertNull($submission->getAttribute('review_notes'));
        $this->assertSame(1, KycSubmission::query()->where('user_id', $user->getKey())->count());
    }
}
