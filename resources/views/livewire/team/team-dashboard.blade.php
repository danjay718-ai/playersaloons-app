<div class="space-y-8">
    <x-ui.toasts />

    <!-- Team Dashboard Header -->
    <div class="bg-gradient-to-r from-zinc-900 via-zinc-900 to-fuchsia-950/10 border border-zinc-850 rounded-2xl p-6 md:p-8 shadow-xl relative overflow-hidden">
        <div class="absolute -top-20 -right-20 w-60 h-60 bg-fuchsia-600/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center space-x-4">
                <div class="bg-zinc-950 p-4 rounded-2xl border border-zinc-800 text-fuchsia-400">
                    <i data-lucide="users" class="w-10 h-10"></i>
                </div>
                <div>
                    <h1 class="text-2xl md:text-4xl font-black font-orbitron tracking-wider text-white uppercase">
                        {{ $team ? $team->name : 'TEAMS HUB' }}
                    </h1>
                    <p class="text-xs text-zinc-400 mt-1">
                        @if($team)
                            Manage your squad, view members, send invitations, and track team status.
                        @else
                            Form a new squad, recruit players, and review pending team invitations.
                        @endif
                    </p>
                </div>
            </div>

            @if($team)
                <div class="bg-zinc-950 border border-zinc-850 rounded-xl px-4 py-2 flex items-center space-x-4">
                    <div>
                        <span class="block text-[10px] text-zinc-500 font-bold uppercase tracking-wider">Status</span>
                        <span class="text-xs font-black font-orbitron text-emerald-400 uppercase tracking-wider">{{ $team->status }}</span>
                    </div>
                    <div class="border-l border-zinc-850 h-8"></div>
                    <div>
                        <span class="block text-[10px] text-zinc-500 font-bold uppercase tracking-wider">Members</span>
                        <span class="text-xs font-black font-orbitron text-zinc-200">{{ $teamMembers->count() }}</span>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if(!$team)
        <!-- NO TEAM STATE -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Create a Team -->
            <div class="lg:col-span-2 bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 shadow-lg shadow-black/20 space-y-6">
                <div class="border-b border-zinc-850 pb-3 flex items-center space-x-2">
                    <i data-lucide="user-plus" class="w-5 h-5 text-fuchsia-400"></i>
                    <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase">
                        CREATE A TEAM
                    </h2>
                </div>

                <form wire:submit="createTeam" class="space-y-4">
                    <div class="space-y-1.5">
                        <label for="teamName" class="block text-xs font-bold text-zinc-400 uppercase tracking-wider">Team Name</label>
                        <input 
                            type="text" 
                            id="teamName" 
                            wire:model="teamName" 
                            class="bg-zinc-950 border border-zinc-800 focus:border-fuchsia-500 rounded-lg px-4 py-2.5 text-sm text-zinc-100 w-full focus:outline-none transition-colors"
                            placeholder="Enter a unique name for your team"
                        >
                        @error('teamName') <span class="text-xs text-red-400 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <button 
                        type="submit" 
                        class="bg-gradient-to-r from-fuchsia-600 to-pink-600 hover:from-fuchsia-500 hover:to-pink-500 text-white font-bold py-2.5 px-6 rounded-lg transition-all text-xs uppercase tracking-wider shadow-lg shadow-fuchsia-900/10 cursor-pointer"
                    >
                        Create Team
                    </button>
                </form>
            </div>

            <!-- Inbound Pending Invitations -->
            <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 shadow-lg shadow-black/20 space-y-6">
                <div class="border-b border-zinc-850 pb-3 flex items-center space-x-2">
                    <i data-lucide="mail-open" class="w-5 h-5 text-indigo-400"></i>
                    <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase">
                        PENDING INVITATIONS
                    </h2>
                </div>

                @if($myPendingInvites->count() > 0)
                    <div class="space-y-3">
                        @foreach($myPendingInvites as $invite)
                            <div class="bg-zinc-950 border border-zinc-850 rounded-xl p-4 space-y-3">
                                <div>
                                    <span class="text-[10px] text-zinc-500 font-semibold block">INVITATION TO JOIN</span>
                                    <h3 class="text-sm font-bold text-zinc-200 mt-0.5">{{ $invite->team->name }}</h3>
                                    <span class="text-[10px] text-zinc-400 mt-1 block">Sent by: {{ $invite->inviter->username }}</span>
                                </div>
                                <div class="flex items-center space-x-2 pt-1">
                                    <button 
                                        wire:click="acceptInvitation('{{ $invite->uuid }}')"
                                        class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-colors cursor-pointer"
                                    >
                                        Accept
                                    </button>
                                    <button 
                                        wire:click="declineInvitation('{{ $invite->uuid }}')"
                                        class="bg-zinc-800 hover:bg-zinc-700 text-zinc-400 font-bold text-xs px-3 py-1.5 rounded-lg transition-colors cursor-pointer"
                                    >
                                        Decline
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12 text-zinc-500">
                        <i data-lucide="mail" class="w-8 h-8 mx-auto text-zinc-650 mb-3"></i>
                        <p class="text-xs font-semibold">No pending team invitations.</p>
                    </div>
                @endif
            </div>
        </div>
    @else
        <!-- USER HAS A TEAM STATE -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Roster Management -->
            <div class="lg:col-span-2 bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 shadow-lg shadow-black/20 space-y-6">
                <div class="border-b border-zinc-850 pb-3 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="users-round" class="w-5 h-5 text-fuchsia-400"></i>
                        <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase">
                            TEAM ROSTER
                        </h2>
                    </div>
                    
                    @if($team->captain_user_id !== auth()->id())
                        <button 
                            wire:click="leaveTeam" 
                            wire:confirm="Are you sure you want to leave this team?"
                            class="bg-red-950/40 hover:bg-red-900/40 border border-red-900/60 text-red-400 font-bold text-xs px-3 py-1.5 rounded-lg transition-all cursor-pointer"
                        >
                            Leave Team
                        </button>
                    @endif
                </div>

                <div class="divide-y divide-zinc-850">
                    @foreach($teamMembers as $member)
                        <div class="py-4 flex items-center justify-between gap-4">
                            <div class="flex items-center space-x-3">
                                <div class="bg-zinc-950 p-2 rounded-xl border border-zinc-800 text-zinc-400">
                                    <i data-lucide="user" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm font-bold text-zinc-200">
                                            {{ $member->user->profile?->display_name ?: $member->user->username }}
                                        </span>
                                        @if($member->role === 'captain')
                                            <span class="text-[8px] font-bold text-amber-400 uppercase tracking-wider bg-amber-950/40 border border-amber-900/60 rounded px-1.5 py-0.5">
                                                Captain
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-[10px] text-zinc-500 font-semibold uppercase">Joined: {{ $member->joined_at->format('M d, Y') }}</span>
                                </div>
                            </div>

                            @if($team->captain_user_id === auth()->id() && $member->user_id !== auth()->id())
                                <div class="flex items-center space-x-2">
                                    <!-- Transfer Captaincy -->
                                    <button 
                                        wire:click="transferCaptaincy('{{ $member->user->username }}')"
                                        wire:confirm="Are you sure you want to transfer captaincy to this member? You will become a regular member."
                                        class="bg-zinc-950 hover:bg-zinc-850 border border-zinc-800 hover:border-amber-900/60 hover:text-amber-400 text-zinc-400 text-[10px] font-bold px-2.5 py-1.5 rounded-lg transition-colors cursor-pointer"
                                        title="Make Captain"
                                    >
                                        Transfer Captaincy
                                    </button>
                                    
                                    <!-- Kick Member -->
                                    <button 
                                        wire:click="removeMember('{{ $member->user->username }}')"
                                        wire:confirm="Are you sure you want to remove this member from the team?"
                                        class="bg-zinc-950 hover:bg-red-950/40 border border-zinc-800 hover:border-red-900/60 hover:text-red-400 text-zinc-400 p-1.5 rounded-lg transition-colors cursor-pointer"
                                        title="Remove Member"
                                    >
                                        <i data-lucide="user-x" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Right: Settings / Invites -->
            <div class="space-y-8">
                <!-- Captain Controls (Invite & Rename & Disband) -->
                @if($team->captain_user_id === auth()->id())
                    <!-- Send Outbound Invitations -->
                    <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 shadow-lg shadow-black/20 space-y-6">
                        <div class="border-b border-zinc-850 pb-3 flex items-center space-x-2">
                            <i data-lucide="user-plus" class="w-5 h-5 text-fuchsia-400"></i>
                            <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase">
                                RECRUIT MEMBERS
                            </h2>
                        </div>

                        <form wire:submit="inviteMember" class="space-y-4">
                            <div class="space-y-1.5">
                                <label for="inviteUsername" class="block text-xs font-bold text-zinc-400 uppercase tracking-wider">Username to Invite</label>
                                <input 
                                    type="text" 
                                    id="inviteUsername" 
                                    wire:model="inviteUsername" 
                                    class="bg-zinc-950 border border-zinc-800 focus:border-fuchsia-500 rounded-lg px-4 py-2.5 text-sm text-zinc-100 w-full focus:outline-none transition-colors"
                                    placeholder="Enter exact username"
                                >
                                @error('inviteUsername') <span class="text-xs text-red-400 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <button 
                                type="submit" 
                                class="w-full bg-gradient-to-r from-fuchsia-600 to-violet-600 hover:from-fuchsia-500 hover:to-violet-500 text-white font-bold py-2.5 rounded-lg transition-all text-xs uppercase tracking-wider cursor-pointer"
                            >
                                Send Invite
                            </button>
                        </form>

                        <!-- Pending Outbound Invites list -->
                        @if($teamPendingInvites->count() > 0)
                            <div class="pt-4 border-t border-zinc-850 space-y-3">
                                <span class="block text-[10px] text-zinc-500 font-bold uppercase tracking-wider">Pending Outbound Invites</span>
                                <div class="space-y-2">
                                    @foreach($teamPendingInvites as $outboundInvite)
                                        <div class="bg-zinc-950 border border-zinc-850 rounded-xl p-3 flex items-center justify-between gap-3 text-xs">
                                            <div class="truncate">
                                                <span class="block font-bold text-zinc-300 truncate">{{ $outboundInvite->invitee->username }}</span>
                                                <span class="text-[9px] text-zinc-500">Expires: {{ $outboundInvite->expires_at->format('M d, Y') }}</span>
                                            </div>
                                            <button 
                                                wire:click="revokeInvitation('{{ $outboundInvite->uuid }}')"
                                                class="text-red-400 hover:text-red-300 font-bold text-[10px] uppercase shrink-0 transition-colors cursor-pointer"
                                            >
                                                Revoke
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Rename / Disband Team -->
                    <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 shadow-lg shadow-black/20 space-y-6">
                        <div class="border-b border-zinc-850 pb-3 flex items-center space-x-2">
                            <i data-lucide="settings" class="w-5 h-5 text-zinc-400"></i>
                            <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase">
                                TEAM SETTINGS
                            </h2>
                        </div>

                        <form wire:submit="updateTeam" class="space-y-4">
                            <div class="space-y-1.5">
                                <label for="editName" class="block text-xs font-bold text-zinc-400 uppercase tracking-wider">Rename Team</label>
                                <input 
                                    type="text" 
                                    id="editName" 
                                    wire:model="editName" 
                                    class="bg-zinc-950 border border-zinc-800 focus:border-zinc-500 rounded-lg px-4 py-2.5 text-sm text-zinc-100 w-full focus:outline-none transition-colors"
                                >
                                @error('editName') <span class="text-xs text-red-400 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <button 
                                type="submit" 
                                class="bg-zinc-850 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 hover:text-white font-bold py-2 px-4 rounded-lg transition-colors text-xs uppercase tracking-wider cursor-pointer"
                            >
                                Save Changes
                            </button>
                        </form>

                        <div class="pt-4 border-t border-zinc-850 space-y-3">
                            <span class="block text-xs font-bold text-red-400 uppercase tracking-wider">Danger Zone</span>
                            <p class="text-[10px] text-zinc-500">Disbanding the team will immediately remove all members, cancel pending invitations, and soft-delete the team record. This cannot be undone.</p>
                            <button 
                                wire:click="disbandTeam" 
                                wire:confirm="ARE YOU ABSOLUTELY SURE you want to disband your team? This action is irreversible."
                                class="w-full bg-red-950/20 hover:bg-red-900/40 border border-red-900/60 hover:border-red-650 text-red-400 font-bold py-2.5 rounded-lg transition-all text-xs uppercase tracking-wider cursor-pointer"
                            >
                                Disband Team
                            </button>
                        </div>
                    </div>
                @else
                    <!-- Non-Captain view of settings -->
                    <div class="bg-zinc-900 border border-zinc-850 rounded-xl p-5 md:p-6 shadow-lg shadow-black/20 space-y-4">
                        <div class="flex items-center space-x-2 text-zinc-400">
                            <i data-lucide="info" class="w-5 h-5 text-indigo-400"></i>
                            <h2 class="text-sm font-bold font-orbitron tracking-wide uppercase">
                                Team Info
                            </h2>
                        </div>
                        <p class="text-xs text-zinc-500 leading-relaxed">
                            Only the team captain (<span class="text-zinc-300 font-semibold">{{ $team->captain?->username }}</span>) is authorized to invite new players, remove members, transfer captaincy, or update team settings.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
