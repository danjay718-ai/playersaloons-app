<?php

declare(strict_types=1);

namespace App\Modules\Match\Jobs;

use App\Modules\Match\Actions\EvaluateAiDisputeAutoResolveAction;
use App\Modules\Match\Models\DisputeAiAnalysis;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\Models\MatchEvidence;
use App\Modules\Match\Services\AiDisputeSettings;
use App\Modules\Match\Services\GeminiDisputeAnalyzer;
use App\Shared\Enums\DisputeStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AnalyzeDisputeWithAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [15, 60];

    public function __construct(
        public readonly int $disputeId,
        public readonly bool $manualTrigger = false,
    ) {
        $this->afterCommit();
        $this->onQueue('default');
    }

    public function handle(
        AiDisputeSettings $settings,
        GeminiDisputeAnalyzer $analyzer,
        EvaluateAiDisputeAutoResolveAction $evaluator,
    ): void {
        if (! $settings->isConfigured()) {
            return;
        }

        if (! $this->manualTrigger && ! $settings->enabled()) {
            return;
        }

        /** @var DisputeAiAnalysis|null $analysis */
        $analysis = DB::transaction(function () use ($settings): ?DisputeAiAnalysis {
            $dispute = MatchDispute::query()->lockForUpdate()->find($this->disputeId);
            if (! $dispute instanceof MatchDispute || $dispute->status === DisputeStatus::RESOLVED) {
                return null;
            }

            $existingCount = DisputeAiAnalysis::query()
                ->where('match_dispute_id', $dispute->id)
                ->count();

            if ($existingCount >= $settings->maxRunsPerDispute()) {
                return null;
            }

            $evidenceCount = MatchEvidence::query()
                ->where('dispute_id', $dispute->id)
                ->count();

            $nextVersion = ((int) DisputeAiAnalysis::query()
                ->where('match_dispute_id', $dispute->id)
                ->max('analysis_version')) + 1;

            return DisputeAiAnalysis::query()->create([
                'uuid' => Str::uuid()->toString(),
                'match_dispute_id' => $dispute->id,
                'analysis_version' => $nextVersion,
                'evidence_count' => $evidenceCount,
                'provider' => $settings->provider(),
                'model' => $settings->model(),
                'status' => DisputeAiAnalysis::STATUS_PENDING,
            ]);
        });

        if (! $analysis instanceof DisputeAiAnalysis) {
            return;
        }

        $dispute = MatchDispute::query()->find($this->disputeId);
        if (! $dispute instanceof MatchDispute) {
            return;
        }

        try {
            $result = $analyzer->analyze($dispute);

            $analysis->forceFill([
                'provider' => $result['provider'],
                'model' => $result['model'],
                'status' => DisputeAiAnalysis::STATUS_COMPLETED,
                'suggested_resolution' => $result['suggested_resolution'],
                'confidence' => number_format($result['confidence'], 4, '.', ''),
                'summary' => $result['summary'],
                'evidence_findings_json' => $result['evidence_findings'],
                'red_flags_json' => $result['red_flags'],
                'prompt_hash' => $result['prompt_hash'],
                'raw_response_json' => $result['raw_response'],
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('AI dispute analysis failed; human admin resolution remains unaffected.', [
                'dispute_id' => $this->disputeId,
                'analysis_id' => $analysis->id,
                'error' => $exception->getMessage(),
            ]);

            $analysis->forceFill([
                'status' => DisputeAiAnalysis::STATUS_FAILED,
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
                'auto_resolve_eligible' => false,
                'auto_resolve_blocked_reason' => 'analysis_failed',
            ])->save();

            return;
        }

        $evaluator->execute($this->disputeId);
    }
}
