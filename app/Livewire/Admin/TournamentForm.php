<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Tournament\Actions\CreateTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;

class TournamentForm extends AdminComponent
{
    use WithFileUploads;

    public bool $isEditMode = false;
    public ?int $tournamentId = null;

    // Form fields
    public string $name = '';
    public int $game_id = 0;
    public int $max_participants = 16;
    public int $min_participants = 4;
    public string $entry_fee = '0.00';
    public string $prize_pool = '0.00';
    public string $registration_open_at = '';
    public string $registration_close_at = '';
    public string $checkin_open_at = '';
    public string $checkin_close_at = '';
    public string $start_at = '';

    public string $description = '';
    public string $rules = '';
    public ?int $platform_id = null;
    public string $frequency = 'daily';
    public ?int $waiting_time = null;
    public ?int $waiting_result_time = null;
    public int $team_size = 1;
    public ?string $prize_1st = null;
    public ?string $prize_2nd = null;
    public ?string $prize_3rd = null;
    public ?int $winning_points = null;
    public $banner;

    public function mount(?int $id = null): void
    {
        $this->rules = $this->getDefaultRules();

        if ($id) {
            $this->isEditMode = true;
            $this->tournamentId = $id;
            $tournament = Tournament::findOrFail($id);

            if ($tournament->status !== TournamentStatus::DRAFT) {
                session()->flash('error', 'Only draft tournaments can be edited.');
                $this->redirect('/admin/tournaments', navigate: true);
                return;
            }

            $this->name = $tournament->name;
            $this->game_id = (int) $tournament->game_id;
            $this->max_participants = (int) $tournament->max_participants;
            $this->min_participants = (int) $tournament->min_participants;
            $this->entry_fee = (string) $tournament->entry_fee;
            $this->prize_pool = (string) $tournament->prize_pool;
            $this->registration_open_at = $tournament->registration_open_at ? $tournament->registration_open_at->format('Y-m-d\TH:i') : '';
            $this->registration_close_at = $tournament->registration_close_at ? $tournament->registration_close_at->format('Y-m-d\TH:i') : '';
            $this->checkin_open_at = $tournament->checkin_open_at ? $tournament->checkin_open_at->format('Y-m-d\TH:i') : '';
            $this->checkin_close_at = $tournament->checkin_close_at ? $tournament->checkin_close_at->format('Y-m-d\TH:i') : '';
            $this->start_at = $tournament->start_at ? $tournament->start_at->format('Y-m-d\TH:i') : '';

            $this->description = $tournament->description ?? '';
            $this->rules = $tournament->rules ?: $this->getDefaultRules();
            $this->platform_id = $tournament->platform_id;
            $this->frequency = $tournament->frequency ?? 'daily';
            $this->waiting_time = $tournament->waiting_time;
            $this->waiting_result_time = $tournament->waiting_result_time;
            $this->team_size = $tournament->team_size ?? 1;
            $this->prize_1st = $tournament->prize_1st !== null ? (string) $tournament->prize_1st : null;
            $this->prize_2nd = $tournament->prize_2nd !== null ? (string) $tournament->prize_2nd : null;
            $this->prize_3rd = $tournament->prize_3rd !== null ? (string) $tournament->prize_3rd : null;
            $this->winning_points = $tournament->winning_points;
        } else {
            $this->game_id = Game::first()?->id ?? 0;
        }
    }

    protected function getDefaultRules(): string
    {
        return "1. Respect all players and admins.\n2. Ensure a stable internet connection.\n3. Check-in is required 15 mins before start.\n4. Disputes must be submitted with screenshots.\n5. Unsportsmanlike behavior will result in disqualification.";
    }

    public function saveTournament(CreateTournamentAction $createAction): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'game_id' => 'required|exists:games,id',
            'max_participants' => 'required|integer|min:2',
            'min_participants' => 'required|integer|min:2|lte:max_participants',
            'entry_fee' => 'required|numeric|min:0',
            'prize_pool' => 'required|numeric|min:0',
            'registration_open_at' => 'required|date',
            'registration_close_at' => 'required|date|after:registration_open_at',
            'checkin_open_at' => 'required|date|after:registration_close_at',
            'checkin_close_at' => 'required|date|after:checkin_open_at',
            'start_at' => 'required|date|after:checkin_close_at',
            'description' => 'nullable|string',
            'rules' => 'nullable|string',
            'platform_id' => 'nullable|exists:platforms,id',
            'frequency' => 'required|string|in:daily,weekly,monthly',
            'waiting_time' => 'nullable|integer|min:0',
            'waiting_result_time' => 'nullable|integer|min:0',
            'team_size' => 'required|integer|min:1',
            'prize_1st' => 'nullable|numeric|min:0',
            'prize_2nd' => 'nullable|numeric|min:0',
            'prize_3rd' => 'nullable|numeric|min:0',
            'winning_points' => 'nullable|integer|min:0',
            'banner' => 'nullable|image|max:2048', // Max 2MB image
        ]);

        $creator = Auth::user();
        if (! $creator) {
            return;
        }

        $data = [
            'name' => $this->name,
            'game_id' => $this->game_id,
            'max_participants' => $this->max_participants,
            'min_participants' => $this->min_participants,
            'entry_fee' => $this->entry_fee,
            'prize_pool' => $this->prize_pool,
            'registration_open_at' => $this->registration_open_at,
            'registration_close_at' => $this->registration_close_at,
            'checkin_open_at' => $this->checkin_open_at,
            'checkin_close_at' => $this->checkin_close_at,
            'start_at' => $this->start_at,
            'description' => $this->description,
            'rules' => $this->rules,
            'platform_id' => $this->platform_id,
            'frequency' => $this->frequency,
            'waiting_time' => $this->waiting_time,
            'waiting_result_time' => $this->waiting_result_time,
            'team_size' => $this->team_size,
            'prize_1st' => $this->prize_1st,
            'prize_2nd' => $this->prize_2nd,
            'prize_3rd' => $this->prize_3rd,
            'winning_points' => $this->winning_points,
        ];

        if ($this->banner) {
            $path = $this->banner->store('tournaments', 'public');
            $data['banner_url'] = '/storage/' . $path;
        }

        if ($this->isEditMode && $this->tournamentId) {
            $tournament = Tournament::findOrFail($this->tournamentId);
            if ($tournament->status !== TournamentStatus::DRAFT) {
                session()->flash('error', 'Only draft tournaments can be edited.');
                return;
            }
            $tournament->update($data);
            session()->flash('success', 'Tournament updated successfully.');
        } else {
            $createAction->execute($data, $creator);
            session()->flash('success', 'Tournament created successfully.');
        }

        $this->redirect('/admin/tournaments', navigate: true);
    }

    public function render()
    {
        $games = Game::with('translations')->get();
        $platforms = Platform::where('is_active', true)->orderBy('name')->get();

        return view('livewire.admin.tournament-form', [
            'games' => $games,
            'platforms' => $platforms,
        ])->layout('components.layouts.admin', [
            'admin_title' => $this->isEditMode ? 'Edit Tournament' : 'Create Tournament',
        ]);
    }
}
