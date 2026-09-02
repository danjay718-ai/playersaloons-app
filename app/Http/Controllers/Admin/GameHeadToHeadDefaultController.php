<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class GameHeadToHeadDefaultController extends Controller
{
    public function edit(Game $game): View
    {
        abort_unless(config('features.tournament_v2.enabled'), 404);
        abort_unless(request()->user()?->can('tournaments.manage'), 403);
        $game->load(['translations', 'platforms:id,name', 'headToHeadDefaults']);

        return view('admin.games.competition-defaults', [
            'game' => $game,
            'defaults' => $game->headToHeadDefaults,
            'title' => 'Game Head-to-Head Template',
            'eyebrow' => 'Game Management · Platform H2H',
            'competitionName' => 'Head-to-Head',
            'bannerColumn' => 'head_to_head_banner_path',
            'bannerInput' => 'head_to_head_banner',
            'updateRoute' => route('admin.games.head-to-head-defaults.update', $game),
        ]);
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        abort_unless(config('features.tournament_v2.enabled'), 404);
        abort_unless($request->user()?->can('tournaments.manage'), 403);
        $data = $request->validate([
            'default_platform_id' => ['required', 'integer', 'exists:platforms,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'rules' => ['nullable', 'string', 'max:20000'],
            'head_to_head_banner' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ]);
        abort_unless($game->platforms()->whereKey($data['default_platform_id'])->exists(), 422, 'Platform is not configured for this game.');
        $existing = $game->headToHeadDefaults;
        $path = $request->file('head_to_head_banner')?->store("games/{$game->id}/head-to-head-templates", 'public')
            ?? $existing?->head_to_head_banner_path;
        $game->headToHeadDefaults()->updateOrCreate(['game_id' => $game->id], [
            ...$data,
            'head_to_head_banner_path' => $path,
        ]);

        return back()->with('success', 'Head-to-Head defaults saved. Existing H2H occurrences remain unchanged.');
    }
}
