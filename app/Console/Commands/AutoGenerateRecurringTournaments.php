<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Tournament\Actions\CreateTournamentAction;
use App\Modules\Identity\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoGenerateRecurringTournaments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tournaments:auto-generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-generates tournaments from recurring templates.';

    /**
     * Execute the console command.
     */
    public function handle(CreateTournamentAction $createAction)
    {
        // Get the system user (SuperAdmin) or first admin to be the creator
        $systemUser = User::role('SUPER_ADMIN')->first() ?? User::first();
        if (!$systemUser) {
            $this->error('No system user found to create tournaments.');
            return;
        }

        $templates = TournamentTemplate::where('is_recurring', true)->get();

        foreach ($templates as $template) {
            $settings = $template->settings_json ?? [];
            $frequency = $settings['frequency'] ?? 'daily';
            $hour = $settings['start_hour'] ?? 18; // Default 6 PM
            $minute = $settings['start_minute'] ?? 0;
            
            // Basic logic: generate the next occurrence if one doesn't exist for the timeframe
            $nextStart = Carbon::today()->setHour($hour)->setMinute($minute);
            
            if ($frequency === 'weekly') {
                $dayOfWeek = $settings['day_of_week'] ?? Carbon::FRIDAY;
                $nextStart = Carbon::today()->next($dayOfWeek)->setHour($hour)->setMinute($minute);
            } elseif ($frequency === 'monthly') {
                $dayOfMonth = $settings['day_of_month'] ?? 1;
                $nextStart = Carbon::today()->setDay($dayOfMonth)->setHour($hour)->setMinute($minute);
                if ($nextStart->isPast()) {
                    $nextStart->addMonth();
                }
            } else { // Daily
                if ($nextStart->isPast()) {
                    $nextStart->addDay();
                }
            }

            // Check if tournament already exists for this template and start time
            $exists = \App\Modules\Tournament\Models\Tournament::where('template_id', $template->id)
                ->where('start_at', $nextStart)
                ->exists();

            if (!$exists) {
                // Calculate checkin and registration windows
                $checkinMinutes = $template->checkin_minutes ?? 15;
                $checkinOpen = $nextStart->copy()->subMinutes($checkinMinutes);
                $registrationClose = $checkinOpen->copy()->subMinutes(1);
                
                // Assuming registration opens 24 hours before start (can be customized)
                $registrationOpen = $nextStart->copy()->subHours(24);

                $data = [
                    'name' => $template->name . ' - ' . $nextStart->format('M d, Y'),
                    'game_id' => $template->game_id,
                    'max_participants' => $template->max_participants,
                    'min_participants' => $template->min_participants,
                    'entry_fee' => $template->entry_fee,
                    'prize_pool' => $settings['prize_pool'] ?? 0, // Fallback if template doesn't specify a fixed pool
                    'registration_open_at' => $registrationOpen,
                    'registration_close_at' => $registrationClose,
                    'checkin_open_at' => $checkinOpen,
                    'checkin_close_at' => $nextStart->copy()->subMinutes(1),
                    'start_at' => $nextStart,
                    'template_id' => $template->id,
                    'description' => $settings['description'] ?? 'Automated tournament from template.',
                    'rules' => $settings['rules'] ?? 'Standard rules apply.',
                    'platform_id' => $settings['platform_id'] ?? null,
                    'frequency' => $frequency,
                    'team_size' => $settings['team_size'] ?? 1,
                    'is_auto_cancel_underfilled' => $template->is_auto_cancel_underfilled ?? false,
                ];

                try {
                    $createAction->execute($data, $systemUser);
                    $this->info("Created recurring tournament from template #{$template->id} for {$nextStart}");
                } catch (\Exception $e) {
                    Log::error("Failed to generate recurring tournament from template {$template->id}: " . $e->getMessage());
                    $this->error("Failed to generate recurring tournament from template #{$template->id}");
                }
            }
        }
    }
}
