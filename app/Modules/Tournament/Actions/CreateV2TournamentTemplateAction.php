<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\CMS\Models\Game;
use App\Modules\Tournament\Models\TournamentScheduleSlot;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Tournament\Support\CompetitionPlatforms;
use App\Shared\Enums\CompetitionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class CreateV2TournamentTemplateAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): TournamentTemplate
    {
        if (! config('features.tournament_v2.enabled')) {
            throw new LogicException('Tournament V2 is not enabled.');
        }

        return DB::transaction(function () use ($data): TournamentTemplate {
            $game = Game::query()->with('platforms:id')->findOrFail((int) $data['game_id']);
            $platformIds = CompetitionPlatforms::validate($game, $data);
            $platformId = $platformIds[0];

            $competitionType = CompetitionType::from($data['competition_type'] ?? CompetitionType::TOURNAMENT->value);
            if ($competitionType === CompetitionType::HEAD_TO_HEAD && (int) $data['max_teams'] !== 2) {
                throw new LogicException('A Head-to-Head template must have exactly two player slots.');
            }
            $firstBps = (int) $data['full_first_bps'];
            $secondBps = (int) $data['full_second_bps'];
            if ((int) $data['max_teams'] <= 4) {
                $firstBps = 9000;
                $secondBps = 0;
            }
            if ($firstBps + $secondBps !== 9000) {
                throw new LogicException('First and Second Prize percentages must total 90%.');
            }

            $template = TournamentTemplate::query()->create([
                'uuid' => Str::uuid()->toString(),
                'workflow_version' => 2,
                'game_id' => $game->id,
                'created_by' => $data['created_by'] ?? null,
                'competition_type' => $competitionType,
                'name' => $data['name'],
                'format' => 'single_elimination',
                'max_participants' => (int) $data['max_teams'],
                'min_participants' => 2,
                'entry_fee' => $data['entry_fee'],
                'prize_model' => 'v2_percentage',
                'checkin_minutes' => 0,
                'is_recurring' => $data['frequency'] !== 'one_time',
                'recurrence_frequency' => $data['frequency'] === 'one_time' ? null : $data['frequency'],
                'timezone' => $data['timezone'],
                'next_run_at' => null,
                'generation_lead_minutes' => 0,
                'is_auto_cancel_underfilled' => false,
                'settings_json' => [
                    'description' => $data['description'] ?? null,
                    'rules' => $data['rules'] ?? null,
                    'banner_url' => $data['banner_url'] ?? null,
                    'platform_id' => $platformId,
                    'platform_ids' => $platformIds,
                    'team_size' => 1,
                    'waiting_result_time' => (int) ($data['waiting_result_time'] ?? 5),
                    'round_duration_seconds' => $data['round_duration_seconds'] ?? null,
                    'winning_points' => $data['winning_points'] ?? 15,
                    'play_xp' => 10,
                    'full_first_bps' => $firstBps,
                    'full_second_bps' => $secondBps,
                    'full_platform_bps' => 1000,
                    'underfilled_first_bps' => 8500,
                    'underfilled_platform_bps' => 1500,
                    'is_featured' => (bool) ($data['is_featured'] ?? false),
                ],
            ]);

            foreach (array_values($data['slots']) as $index => $slot) {
                $overrides = $slot['overrides'] ?? [];
                if ($competitionType === CompetitionType::HEAD_TO_HEAD && isset($overrides['max_teams']) && (int) $overrides['max_teams'] !== 2) {
                    throw new LogicException('A Head-to-Head slot cannot override the two-player limit.');
                }
                if (isset($overrides['platform_id']) && ! $game->platforms->contains('id', (int) $overrides['platform_id'])) {
                    throw new LogicException('A schedule slot platform is not configured for this game.');
                }
                if (isset($overrides['platform_ids']) || isset($overrides['platform_id'])) {
                    $overrides['platform_ids'] = CompetitionPlatforms::validate($game, $overrides);
                    $overrides['platform_id'] = $overrides['platform_ids'][0];
                }
                $slotFirst = (int) ($overrides['full_first_bps'] ?? $firstBps);
                $slotSecond = (int) ($overrides['full_second_bps'] ?? $secondBps);
                if ($slotFirst + $slotSecond !== 9000) {
                    throw new LogicException('Every schedule slot First and Second Prize split must total 90%.');
                }
                TournamentScheduleSlot::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    'tournament_template_id' => $template->id,
                    'identity_key' => $this->slotIdentity((string) $data['frequency'], $slot),
                    'label' => $slot['label'] ?? null,
                    'local_start_time' => $slot['local_start_time'],
                    'schedule_start_at' => $slot['schedule_start_at'] ?? null,
                    'schedule_end_at' => $slot['schedule_end_at'] ?? null,
                    'day_of_week' => $slot['day_of_week'] ?? null,
                    'day_of_month' => $slot['day_of_month'] ?? null,
                    'overrides_json' => $overrides ?: null,
                    'is_active' => true,
                    'sort_order' => $index,
                ]);
            }

            return $template->load('scheduleSlots');
        });
    }

    /** @param array<string, mixed> $slot */
    private function slotIdentity(string $frequency, array $slot): string
    {
        $day = match ($frequency) {
            'weekly' => 'w'.(int) ($slot['day_of_week'] ?? 0),
            'monthly' => 'm'.(int) ($slot['day_of_month'] ?? 1),
            'one_time' => 'once',
            default => 'd',
        };

        return $day.':'.$slot['local_start_time'];
    }
}
