<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Listeners;

use App\Modules\Community\Models\Notification;
use App\Modules\Community\Services\NotificationService;
use App\Modules\Tournament\Events\TournamentCancelled;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Wallet\Events\RefundIssued;
use App\Modules\Wallet\Models\Refund;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueRefundsListener
{
    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = 'wallet';

    public function __construct(
        private readonly WalletService $walletService,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(TournamentCancelled $event): void
    {
        if (! $event->refundRequired) {
            return;
        }

        /** @var Tournament|null $tournament */
        $tournament = Tournament::query()->find($event->tournamentId);

        if ($tournament === null) {
            return;
        }

        DB::transaction(function () use ($tournament): void {
            // Find cancelled registrations that were paid
            /** @var Collection<int, TournamentRegistration> $registrations */
            $registrations = TournamentRegistration::query()
                ->where('tournament_id', $tournament->getKey())
                ->where('payment_status', PaymentStatus::PAID)
                ->get();

            $entryFee = (float) ($tournament->entry_fee ?? '0.00');

            foreach ($registrations as $registration) {
                $user = $registration->user;
                if ($user === null || $user->wallet === null) {
                    continue;
                }

                $refundRef = Str::uuid()->toString();

                // Create Refund record
                /** @var Refund $refund */
                $refund = Refund::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    'wallet_id' => $user->wallet->getKey(),
                    'tournament_id' => $tournament->getKey(),
                    'amount' => $entryFee,
                    'status' => 'completed',
                    'refund_reference_uuid' => $refundRef,
                    'created_at' => now(),
                ]);

                // Credit player's wallet
                $this->walletService->credit(
                    $user->wallet,
                    $entryFee,
                    LedgerType::REFUND,
                    Refund::class,
                    (string) $refund->getKey(),
                    "Refund: tournament '{$tournament->name}' was cancelled"
                );

                // Update registration payment status
                $registration->payment_status = PaymentStatus::REFUNDED;
                $registration->save();

                // Send notification using NotificationService (respects preferences and dispatches realtime)
                $this->notificationService->send(
                    $user,
                    'refund',
                    'Tournament Refunded',
                    "Your entry fee of {$entryFee} for tournament '{$tournament->name}' has been refunded because the tournament was cancelled."
                );

                RefundIssued::dispatch(
                    (int) $user->wallet->getKey(),
                    (int) $refund->getKey(),
                    (int) $tournament->getKey(),
                    (string) $entryFee
                );
            }
        });
    }
}
