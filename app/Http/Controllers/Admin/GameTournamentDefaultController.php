<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class GameTournamentDefaultController extends Controller
{
    public function edit(Game $game): View
    {
        abort_unless(config('features.tournament_v2.enabled'), 404);
        abort_unless(request()->user()?->can('tournaments.manage'), 403);
        $game->load(['translations', 'platforms:id,name', 'tournamentDefaults']);

        return view('admin.games.competition-defaults', [
            'game' => $game,
            'defaults' => $game->tournamentDefaults,
            'title' => 'Game Tournament Template',
            'eyebrow' => 'Game Management · Tournament V2',
            'competitionName' => 'Tournament',
            'bannerColumn' => 'tournament_banner_path',
            'bannerInput' => 'tournament_banner',
            'updateRoute' => route('admin.games.tournament-defaults.update', $game),
        ]);
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        abort_unless(config('features.tournament_v2.enabled'), 404);
        abort_unless($request->user()?->can('tournaments.manage'), 403);
        $data = $request->validate([
            'default_platform_id' => ['required', 'integer', 'exists:platforms,id,deleted_at,NULL'],
            'description' => ['nullable', 'string', 'max:10000'],
            'rules' => ['nullable', 'string', 'max:20000'],
            'tournament_banner' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ]);
        abort_unless($game->platforms()->whereKey($data['default_platform_id'])->exists(), 422, 'Platform is not configured for this game.');
        $existing = $game->tournamentDefaults;
        $path = $request->file('tournament_banner')?->store("games/{$game->id}/tournament-templates", 'public')
            ?? $existing?->tournament_banner_path;
        $game->tournamentDefaults()->updateOrCreate(['game_id' => $game->id], [
            ...$data,
            'tournament_banner_path' => $path,
        ]);

        return back()->with('success', 'Tournament defaults saved. Existing occurrences remain unchanged.');
    }
}
