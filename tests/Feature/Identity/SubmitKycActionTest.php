<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Actions\SubmitKycAction;
use App\Modules\Identity\Events\UserKycSubmitted;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\KycStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
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

    public function test_player_can_submit_kyc_documents(): void
    {
        Storage::fake('local');
        Event::fake([UserKycSubmitted::class]);

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
        $this->assertSame($paths[0], $submission->document_front_path);
        $this->assertNull($submission->document_back_path);

        $this->assertDatabaseHas('kyc_submissions', [
            'user_id' => $user->getKey(),
            'status' => 'submitted',
        ]);

        Event::assertDispatched(UserKycSubmitted::class, function (UserKycSubmitted $e) use ($user, $submission): bool {
            return $e->userId === (int) $user->getKey()
                && $e->kycSubmissionId === (int) $submission->getKey();
        });
    }

    public function test_player_cannot_submit_kyc_twice_while_pending(): void
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

    public function test_kyc_transition_from_rejected_to_submitted(): void
    {
        Storage::fake('local');
        Event::fake([UserKycSubmitted::class]);

        $user = $this->createUser();

        $rejected = KycSubmission::query()->create([
            'uuid' => fake()->uuid(),
            'user_id' => $user->getKey(),
            'status' => KycStatus::REJECTED,
            'document_type' => 'passport',
            'document_paths' => ['kyc/old.jpg'],
            'review_notes' => 'blurry',
        ]);

        // Use 'id_card' — matches the allowed values in ProfileDashboard: passport, id_card, drivers_license
        $submission = app(SubmitKycAction::class)->execute($user, 'id_card', [
            UploadedFile::fake()->create('id-card.png', 64, 'image/png'),
        ]);

        // Reuses the same row (no new record created)
        $this->assertSame($rejected->getKey(), $submission->getKey());
        $this->assertSame(KycStatus::SUBMITTED, $submission->getAttribute('status'));
        $this->assertSame('id_card', $submission->getAttribute('document_type'));
        $this->assertNull($submission->getAttribute('review_notes'));
        $this->assertSame(1, KycSubmission::query()->where('user_id', $user->getKey())->count());
    }
}
