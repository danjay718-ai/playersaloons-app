<?php

declare(strict_types=1);

namespace App\Modules\Match\Services;

use App\Modules\Operations\Services\SystemSettings;

final class AiDisputeSettings
{
    public const MODE_SHADOW = 'shadow';

    public const MODE_AUTO_RESOLVE = 'auto_resolve';

    public function __construct(private readonly SystemSettings $settings) {}

    public function enabled(): bool
    {
        return filter_var($this->settings->get('ai.disputes.enabled', 'false'), FILTER_VALIDATE_BOOL);
    }

    public function isConfigured(): bool
    {
        return trim((string) config('services.gemini.api_key', '')) !== '';
    }

    public function isActive(): bool
    {
        return $this->enabled() && $this->isConfigured();
    }

    public function mode(): string
    {
        $mode = strtolower(trim((string) $this->settings->get('ai.disputes.mode', self::MODE_SHADOW)));

        return in_array($mode, [self::MODE_SHADOW, self::MODE_AUTO_RESOLVE], true)
            ? $mode
            : self::MODE_SHADOW;
    }

    public function minConfidence(): float
    {
        $value = (float) $this->settings->get('ai.disputes.min_confidence', '0.95');

        return max(0.50, min(1.00, $value));
    }

    public function maxPrizeCap(): string
    {
        $value = (float) $this->settings->get('ai.disputes.max_prize_cap', '50.00');

        return number_format(max(0.0, $value), 2, '.', '');
    }

    public function evidenceWindowMinutes(): int
    {
        $value = (int) $this->settings->get('ai.disputes.evidence_window_minutes', '10');

        return max(1, min(1440, $value));
    }

    public function maxRunsPerDispute(): int
    {
        $value = (int) $this->settings->get('ai.disputes.max_runs_per_dispute', '3');

        return max(1, min(10, $value));
    }

    public function model(): string
    {
        $model = trim((string) config('services.gemini.dispute_model', 'gemini-2.5-flash'));

        return $model !== '' ? $model : 'gemini-2.5-flash';
    }
}
