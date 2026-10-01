@if($isParticipant || $isAdmin)
    @php
        $isRematch = (int) ($match->active_attempt_number ?? 1) > 1
            || in_array($match->resolution_reason, ['rematch', 'admin_draw_rematch', 'admin_rematch', 'agreed_rematch'], true)
            || $match->attempts->contains(fn ($attempt) => $attempt->status === 'rematch');
    @endphp
    <div class="decorated-card match-connection-card rounded-2xl border border-cyan-900/40 bg-cyan-950/10 p-5 md:p-6">
        <i data-lucide="wifi" aria-hidden="true" class="ui-card-watermark"></i>
        <div class="mb-5 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div><h2 class="font-orbitron text-sm font-black uppercase tracking-widest text-cyan-100">Game & Connection Details</h2><p class="mt-1 text-xs text-zinc-500">Everything needed to find the opponent and start this match.</p></div>
            <span class="rounded-full border border-zinc-800 bg-zinc-950 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-zinc-400">{{ $match->tournament->timezone ?: config('app.tournament_timezone') }}</span>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-4"><span class="block text-[9px] font-black uppercase tracking-widest text-zinc-600">Game / Platforms</span><strong class="mt-1 block text-sm text-white">{{ $match->tournament->game->localizedName() }}</strong><span class="text-xs text-zinc-500">{{ $match->tournament->platform_names ?: 'Platform not set' }}</span></div>
            <div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-4"><span class="block text-[9px] font-black uppercase tracking-widest text-zinc-600">Side A Game IDs</span><span class="block text-[10px] text-zinc-500">{{ $match->playerARegistration?->platform?->name ?? $match->tournament->platform?->name ?? 'Platform not set' }}</span>@forelse($match->playerARegistration?->tournamentTeam?->members ?? [] as $member)<span class="mt-1 block break-all text-xs text-cyan-300">{{ $member->user?->username }} · {{ $member->game_id_value ?: 'Not provided' }}</span>@empty<strong class="mt-1 block break-all text-sm text-cyan-300">{{ $match->playerARegistration?->game_id_value ?: 'Not provided' }}</strong>@endforelse</div>
            <div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-4"><span class="block text-[9px] font-black uppercase tracking-widest text-zinc-600">Side B Game IDs</span><span class="block text-[10px] text-zinc-500">{{ $match->playerBRegistration?->platform?->name ?? $match->tournament->platform?->name ?? 'Platform not set' }}</span>@forelse($match->playerBRegistration?->tournamentTeam?->members ?? [] as $member)<span class="mt-1 block break-all text-xs text-fuchsia-300">{{ $member->user?->username }} · {{ $member->game_id_value ?: 'Not provided' }}</span>@empty<strong class="mt-1 block break-all text-sm text-fuchsia-300">{{ $match->playerBRegistration?->game_id_value ?: 'Not provided' }}</strong>@endforelse</div>
            <div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-4"><span class="block text-[9px] font-black uppercase tracking-widest text-zinc-600">Server / Region</span><strong class="mt-1 block text-sm text-white">{{ $match->server_region ?: 'Follow game default' }}</strong></div>
            @if($match->lobby_code || $match->lobby_password)
                <div class="rounded-xl border border-violet-800/50 bg-violet-950/20 p-4"><span class="block text-[9px] font-black uppercase tracking-widest text-violet-400">Lobby Code</span><strong class="mt-1 block text-base text-white">{{ $match->lobby_code ?: '—' }}</strong></div>
                <div class="rounded-xl border border-violet-800/50 bg-violet-950/20 p-4"><span class="block text-[9px] font-black uppercase tracking-widest text-violet-400">Lobby Password</span><strong class="mt-1 block text-base text-white">{{ $match->lobby_password ?: '—' }}</strong></div>
            @endif
            @if($match->lobby_instructions)<div class="rounded-xl border border-zinc-800 bg-zinc-950/70 p-4 md:col-span-2"><span class="block text-[9px] font-black uppercase tracking-widest text-zinc-600">Instructions</span><p class="mt-1 whitespace-pre-line text-xs leading-relaxed text-zinc-300">{{ $match->lobby_instructions }}</p></div>@endif
        </div>

        @if($isParticipant && $match->status->value === 'ready')
            <div class="mt-5 rounded-xl border border-zinc-800 bg-zinc-950/60 p-4">
                <p class="text-xs font-bold text-white">{{ $isRematch ? 'This is a rematch' : 'Match starts automatically' }}</p>
                <p class="mt-1 text-[11px] text-zinc-400">{{ $match->tournament->status === \App\Shared\Enums\TournamentStatus::ONGOING ? 'The match is starting. Result submission will open shortly.' : 'Result submission opens when the tournament starts.' }}</p>
            </div>
        @endif

        @if($isParticipant && $match->status->value === 'in_progress')
            <div role="{{ $isRematch ? 'alert' : 'status' }}" class="mt-5 flex items-center gap-3 rounded-xl border p-4 {{ $isRematch ? 'border-cyan-400/40 bg-cyan-500/10' : 'border-emerald-800/50 bg-emerald-950/20' }}">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $isRematch ? 'bg-cyan-400/15 text-cyan-300' : 'bg-emerald-500/15 text-emerald-300' }}"><i data-lucide="{{ $isRematch ? 'refresh-cw' : 'check-circle-2' }}" class="h-5 w-5"></i></span>
                @if($isRematch)
                    <div><p class="text-xs font-black uppercase tracking-wider text-cyan-200">This is a rematch — play again</p><p class="mt-1 text-[11px] text-cyan-100/75">The previous attempt ended without a winner. Play this rematch and submit a new result{{ $isSubmitter ? '; your new result is submitted and the opponent still needs to respond' : '' }}.</p></div>
                @else
                    <div><p class="text-xs font-black uppercase tracking-wider text-emerald-200">Match in progress — play now</p><p class="mt-1 text-[11px] text-zinc-400">{{ $isAdmin ? 'Play the match, then submit your result below.' : __('Play the match, then submit your result above.') }}</p></div>
                @endif
            </div>
        @endif

        @if($isAdmin)
            <form wire:submit.prevent="saveMatchRoomDetails" class="mt-5 grid grid-cols-1 gap-3 border-t border-zinc-800 pt-5 md:grid-cols-2 lg:grid-cols-4">
                <input wire:model="lobbyCode" type="text" placeholder="Lobby code" class="rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-xs text-white">
                <input wire:model="lobbyPassword" type="text" placeholder="Lobby password" class="rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-xs text-white">
                <input wire:model="serverRegion" type="text" placeholder="Server / region" class="rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-xs text-white">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-white">Save Match Room</button>
                <textarea wire:model="lobbyInstructions" rows="2" placeholder="Connection instructions" class="rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2 text-xs text-white md:col-span-2 lg:col-span-4"></textarea>
            </form>
        @endif
    </div>
@endif
