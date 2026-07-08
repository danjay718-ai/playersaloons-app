<div
    class="min-h-[calc(100vh-5rem)]"
    x-data="chatConsole({
        endpoints: {
            conversations: '{{ route('chat.conversations') }}',
            users: '{{ route('chat.users') }}',
            players: '{{ url('/chat/api/players') }}',
            direct: '{{ route('chat.direct.open') }}',
            team: '{{ url('/chat/api/teams') }}',
            teamJoin: '{{ url('/chat/api/teams') }}',
            messages: '{{ url('/chat/api/conversations') }}'
        },
        csrf: '{{ csrf_token() }}',
        currentUserUuid: '{{ auth()->user()->uuid }}'
    })"
    x-init="init()"
>
    <div class="grid min-h-[calc(100vh-7rem)] grid-cols-1 gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
        <aside class="rounded-xl border border-purple-500/20 bg-[#080516]/90 shadow-[0_0_30px_rgba(88,28,135,0.18)] overflow-hidden">
            <div class="border-b border-purple-500/15 bg-purple-950/20 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-orbitron text-[10px] font-black uppercase tracking-[0.28em] text-fuchsia-300">Realtime Channels</p>
                        <h2 class="mt-1 font-orbitron text-lg font-black uppercase tracking-widest text-zinc-100">Comms Hub</h2>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2">
                    <button type="button" @click="filter = 'all'" :class="filter === 'all' ? activeFilterClass : idleFilterClass">All</button>
                    <button type="button" @click="filter = 'direct'" :class="filter === 'direct' ? activeFilterClass : idleFilterClass">P2P</button>
                    <button type="button" @click="filter = 'team'" :class="filter === 'team' ? activeFilterClass : idleFilterClass">Teams</button>
                </div>
            </div>

            <div class="space-y-4 p-4">
                <form @submit.prevent="openDirect()" class="rounded-lg border border-zinc-800 bg-zinc-950/50 p-3">
                    <label class="font-orbitron text-[9px] font-bold uppercase tracking-widest text-zinc-500">Search player</label>
                    <div class="mt-2 flex gap-2">
                        <input
                            x-model="playerSearch"
                            @input.debounce.250ms="searchPlayers()"
                            type="text"
                            autocomplete="off"
                            placeholder="player username"
                            class="min-w-0 flex-1 rounded-lg border border-zinc-800 bg-black/40 px-3 py-2 text-xs text-zinc-200 outline-none transition focus:border-fuchsia-500"
                        >
                        <button type="submit" class="rounded-lg border border-fuchsia-400/30 bg-fuchsia-600/20 px-3 text-xs font-black uppercase tracking-widest text-fuchsia-200 hover:bg-fuchsia-600/30">
                            Link
                        </button>
                    </div>
                    <p x-show="searchError" x-text="searchError" class="mt-2 text-[10px] text-red-300"></p>
                    <div x-show="playerResults.length > 0" class="mt-2 overflow-hidden rounded-lg border border-zinc-800">
                        <template x-for="player in playerResults" :key="player.uuid">
                            <div class="flex items-center justify-between gap-2 border-b border-zinc-800/70 bg-black/20 px-3 py-2 last:border-b-0">
                                <button type="button" @click="openPlayerProfile(player.uuid)" class="min-w-0 flex items-center gap-2 text-left">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-[9px] font-black text-emerald-200" x-text="player.initials"></span>
                                    <span class="truncate text-xs font-bold text-zinc-200" x-text="player.username"></span>
                                </button>
                                <button type="button" @click="openDirect(player.username)" class="rounded-md border border-fuchsia-400/30 px-2 py-1 text-[9px] font-black uppercase tracking-widest text-fuchsia-200">
                                    Message
                                </button>
                            </div>
                        </template>
                    </div>
                </form>

                <template x-if="teams.length > 0">
                    <div class="rounded-lg border border-zinc-800 bg-zinc-950/50 p-3">
                        <label class="font-orbitron text-[9px] font-bold uppercase tracking-widest text-zinc-500">Team channels</label>
                        <div class="mt-2 grid gap-2">
                            <template x-for="team in teams" :key="team.uuid">
                                <button type="button" @click="team.joined ? openTeam(team.uuid) : requestJoinTeam(team)" class="flex items-center justify-between rounded-lg border border-cyan-500/20 bg-cyan-950/10 px-3 py-2 text-left hover:border-cyan-400/40">
                                    <span class="min-w-0">
                                        <span class="block truncate text-xs font-bold text-cyan-100" x-text="team.name"></span>
                                        <span class="block text-[9px] uppercase tracking-widest" :class="team.joined ? 'text-emerald-300' : 'text-zinc-600'" x-text="team.joined ? 'Joined' : 'Join to chat'"></span>
                                    </span>
                                    <i :data-lucide="team.joined ? 'radio' : 'log-in'" class="h-3.5 w-3.5 text-cyan-300"></i>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="max-h-[42vh] overflow-y-auto border-t border-purple-500/10 p-2 xl:max-h-[calc(100vh-27rem)]">
                <template x-for="conversation in filteredConversations()" :key="conversation.uuid">
                    <button
                        type="button"
                        @click="selectConversation(conversation)"
                        :class="selected?.uuid === conversation.uuid ? 'border-purple-400/50 bg-purple-950/35' : 'border-transparent bg-transparent hover:border-zinc-700 hover:bg-zinc-900/60'"
                        class="mb-1 flex w-full items-center gap-3 rounded-lg border p-3 text-left transition"
                    >
                        <div :class="conversation.type === 'global' ? 'from-fuchsia-500 to-purple-600' : conversation.type === 'team' ? 'from-cyan-500 to-blue-600' : 'from-emerald-500 to-teal-600'" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br text-[10px] font-black text-white shadow-[0_0_16px_rgba(168,85,247,0.25)]">
                            <span x-text="conversation.type === 'global' ? 'GL' : conversation.type === 'team' ? 'TM' : 'P2'"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-black uppercase tracking-wider text-zinc-100" x-text="conversation.title"></p>
                            <p class="truncate text-[10px] text-zinc-500" x-text="conversation.subtitle"></p>
                        </div>
                        <span
                            x-show="unread[conversation.uuid] || conversation.is_unread"
                            class="h-2.5 w-2.5 shrink-0 rounded-full bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.9)]"
                            aria-label="Unread chat"
                        ></span>
                    </button>
                </template>
            </div>
        </aside>

        <section class="flex min-h-[680px] flex-col overflow-hidden rounded-xl border border-purple-500/20 bg-[#070412]/95 shadow-[0_0_35px_rgba(88,28,135,0.18)]">
            <header class="flex items-center justify-between gap-3 border-b border-purple-500/15 bg-gradient-to-r from-purple-950/40 via-zinc-950 to-cyan-950/20 p-4">
                <div class="min-w-0">
                    <p class="font-orbitron text-[9px] font-bold uppercase tracking-[0.35em] text-purple-300" x-text="selected?.type === 'direct' ? 'Secure P2P Link' : selected?.type === 'team' ? 'Squad Channel' : 'Global Relay'"></p>
                    <h3 class="mt-1 truncate font-orbitron text-base font-black uppercase tracking-widest text-zinc-100" x-text="selected?.title || 'Loading channel...'"></h3>
                </div>
            </header>

            <div x-ref="messagePane" class="flex-1 space-y-3 overflow-y-auto p-4">
                <template x-if="bootError">
                    <div class="flex h-full items-center justify-center text-center">
                        <div class="max-w-md rounded-xl border border-red-500/25 bg-red-950/10 p-5">
                            <i data-lucide="wifi-off" class="mx-auto h-10 w-10 text-red-300"></i>
                            <p class="mt-3 font-orbitron text-xs font-black uppercase tracking-widest text-red-200">Comms sync failed</p>
                            <p class="mt-2 text-xs text-red-100/80" x-text="bootError"></p>
                            <button type="button" @click="bootError = ''; loadingMessages = true; init()" class="mt-4 rounded-lg border border-red-300/30 px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-red-100">
                                Retry
                            </button>
                        </div>
                    </div>
                </template>

                <div x-show="loadingMessages" class="flex h-full items-center justify-center">
                    <div class="rounded-xl border border-purple-500/20 bg-purple-950/10 px-4 py-3 text-xs font-bold uppercase tracking-widest text-purple-200">Syncing comms...</div>
                </div>

                <template x-if="!bootError && !loadingMessages && messages.length === 0">
                    <div class="flex h-full items-center justify-center text-center">
                        <div>
                            <i data-lucide="message-square-more" class="mx-auto h-10 w-10 text-zinc-700"></i>
                            <p class="mt-3 font-orbitron text-xs font-black uppercase tracking-widest text-zinc-400">No transmissions yet</p>
                            <p class="mt-1 text-xs text-zinc-600">Send the first message to open the channel.</p>
                        </div>
                    </div>
                </template>

                <template x-for="message in messages" :key="message.uuid">
                    <div :class="message.user.uuid === currentUserUuid ? 'justify-end' : 'justify-start'" class="flex">
                        <div class="flex max-w-[88%] items-start gap-2" :class="message.user.uuid === currentUserUuid ? 'flex-row-reverse' : ''">
                            <button type="button" @click="openPlayerProfile(message.user.uuid)" class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-purple-500/20 bg-gradient-to-br from-fuchsia-500/30 to-cyan-500/30 text-[10px] font-black text-white">
                                <template x-if="message.user.avatar_url">
                                    <img :src="message.user.avatar_url" :alt="message.user.username" class="h-full w-full object-cover">
                                </template>
                                <span x-show="!message.user.avatar_url" x-text="message.user.initials"></span>
                            </button>
                            <div :class="message.user.uuid === currentUserUuid ? 'border-fuchsia-500/25 bg-fuchsia-950/20' : 'border-zinc-800 bg-zinc-950/70'" class="rounded-xl border px-3 py-2">
                                <div class="mb-1 flex items-center gap-2" :class="message.user.uuid === currentUserUuid ? 'justify-end' : ''">
                                    <button type="button" @click="openPlayerProfile(message.user.uuid)" class="font-orbitron text-[10px] font-black uppercase tracking-wider hover:underline" :class="message.user.uuid === currentUserUuid ? 'text-fuchsia-200' : 'text-cyan-200'" x-text="message.user.username"></button>
                                    <span class="text-[9px] text-zinc-600" x-text="message.time"></span>
                                </div>
                                <p class="whitespace-pre-wrap break-words text-sm leading-relaxed text-zinc-200" x-text="message.body"></p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <form @submit.prevent="sendMessage()" class="border-t border-purple-500/15 bg-black/20 p-4">
                <div class="flex gap-2">
                    <textarea
                        x-model="draft"
                        @keydown.enter.prevent="if (!$event.shiftKey) sendMessage(); else draft += '\n'"
                        rows="1"
                        maxlength="1000"
                        placeholder="Transmit message..."
                        class="max-h-32 min-h-[44px] flex-1 resize-none rounded-xl border border-zinc-800 bg-zinc-950/80 px-4 py-3 text-sm text-zinc-100 outline-none transition placeholder:text-zinc-600 focus:border-fuchsia-500"
                    ></textarea>
                    <button type="submit" :disabled="sending || draft.trim().length === 0" class="rounded-xl border border-fuchsia-400/30 bg-fuchsia-600/25 px-4 font-orbitron text-xs font-black uppercase tracking-widest text-fuchsia-100 transition hover:bg-fuchsia-600/40 disabled:cursor-not-allowed disabled:opacity-40">
                        Send
                    </button>
                </div>
                <div class="mt-2 flex justify-between text-[10px] text-zinc-600">
                    <span x-show="error" x-text="error" class="text-red-300"></span>
                    <span class="ml-auto" x-text="`${draft.length}/1000`"></span>
                </div>
            </form>
        </section>
    </div>

    <div x-show="playerModalOpen" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
        <div @click.outside="playerModalOpen = false" class="w-full max-w-xl overflow-hidden rounded-2xl border border-purple-500/25 bg-[#080516] shadow-[0_0_50px_rgba(168,85,247,0.25)]">
            <div class="border-b border-purple-500/15 bg-gradient-to-r from-purple-950/40 to-cyan-950/20 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-fuchsia-500 to-cyan-500 font-orbitron text-sm font-black text-white" x-text="selectedPlayer?.initials || 'PS'"></div>
                        <div class="min-w-0">
                            <p class="font-orbitron text-[10px] font-black uppercase tracking-[0.3em] text-purple-300">Player Profile</p>
                            <h3 class="truncate font-orbitron text-xl font-black uppercase tracking-widest text-zinc-100" x-text="selectedPlayer?.display_name || 'Loading...'"></h3>
                            <p class="text-xs text-zinc-500" x-text="selectedPlayer?.username ? `@${selectedPlayer.username}` : ''"></p>
                        </div>
                    </div>
                    <button type="button" @click="playerModalOpen = false" class="rounded-lg border border-zinc-800 p-2 text-zinc-400 hover:text-white">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
            </div>

            <div class="p-5">
                <div x-show="playerLoading" class="py-10 text-center text-xs font-bold uppercase tracking-widest text-purple-200">Loading player stats...</div>
                <div x-show="selectedPlayer?.error" class="rounded-xl border border-red-500/25 bg-red-950/10 p-4 text-sm text-red-200" x-text="selectedPlayer?.error"></div>

                <div x-show="selectedPlayer && !selectedPlayer.error && !playerLoading" class="space-y-5">
                    <p class="text-sm leading-relaxed text-zinc-400" x-text="selectedPlayer?.bio || 'No bio yet.'"></p>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-3">
                            <p class="text-[9px] uppercase tracking-widest text-zinc-600">Record</p>
                            <p class="mt-1 font-orbitron text-lg font-black text-zinc-100" x-text="`${selectedPlayer?.stats?.wins || 0}-${selectedPlayer?.stats?.losses || 0}`"></p>
                        </div>
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-3">
                            <p class="text-[9px] uppercase tracking-widest text-zinc-600">Win Rate</p>
                            <p class="mt-1 font-orbitron text-lg font-black text-emerald-300" x-text="selectedPlayer?.stats?.win_rate || '0%'"></p>
                        </div>
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-3">
                            <p class="text-[9px] uppercase tracking-widest text-zinc-600">Matches</p>
                            <p class="mt-1 font-orbitron text-lg font-black text-cyan-300" x-text="selectedPlayer?.stats?.matches || 0"></p>
                        </div>
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-3">
                            <p class="text-[9px] uppercase tracking-widest text-zinc-600">Prizes</p>
                            <p class="mt-1 font-orbitron text-lg font-black text-fuchsia-300" x-text="selectedPlayer?.stats?.prize_total || '$0.00'"></p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-purple-500/15 bg-purple-950/10 p-3">
                        <div class="text-xs text-zinc-400">
                            <span class="font-bold text-zinc-200" x-text="selectedPlayer?.followers || 0"></span>
                            followers
                            <span class="mx-2 text-zinc-700">/</span>
                            joined <span x-text="selectedPlayer?.joined || 'recently'"></span>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" x-show="!selectedPlayer?.is_self" @click="followSelectedPlayer()" class="rounded-lg border border-emerald-400/30 bg-emerald-600/15 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-emerald-200">
                                <span x-text="selectedPlayer?.is_following ? 'Following' : 'Follow'"></span>
                            </button>
                            <button type="button" x-show="!selectedPlayer?.is_self" @click="messageSelectedPlayer()" class="rounded-lg border border-fuchsia-400/30 bg-fuchsia-600/20 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-fuchsia-100">
                                Message
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-show="teamJoinModalOpen" x-cloak class="fixed inset-0 z-[85] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">
        <div @click.outside="teamJoinModalOpen = false" class="w-full max-w-lg overflow-hidden rounded-2xl border border-cyan-500/25 bg-[#080516] shadow-[0_0_50px_rgba(34,211,238,0.18)]">
            <div class="border-b border-cyan-500/15 bg-cyan-950/20 p-5">
                <p class="font-orbitron text-[10px] font-black uppercase tracking-[0.3em] text-cyan-300">Join Team Channel</p>
                <h3 class="mt-1 font-orbitron text-lg font-black uppercase tracking-widest text-zinc-100" x-text="pendingTeamJoin?.name || 'Team Channel'"></h3>
            </div>
            <div class="space-y-4 p-5">
                <p class="text-sm leading-relaxed text-zinc-300">
                    Team channels are tied to team membership. Joining this channel will add you to this team.
                    <template x-if="currentTeam">
                        <span> You will automatically leave <strong class="text-amber-200" x-text="currentTeam.name"></strong> and its team chat.</span>
                    </template>
                </p>
                <div class="rounded-xl border border-amber-500/20 bg-amber-950/10 p-3 text-xs text-amber-100">
                    This changes your active team membership. Continue only if you are sure.
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="teamJoinModalOpen = false; pendingTeamJoin = null" class="rounded-lg border border-zinc-700 px-4 py-2 text-xs font-bold uppercase tracking-widest text-zinc-300">
                        Cancel
                    </button>
                    <button type="button" @click="confirmJoinTeam()" class="rounded-lg border border-cyan-400/40 bg-cyan-600/20 px-4 py-2 text-xs font-black uppercase tracking-widest text-cyan-100">
                        Join Channel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
