<div class="space-y-8">
    <!-- Messages / Error Flash -->
    @if(session()->has('message'))
        <div class="bg-emerald-950/30 border border-emerald-900/50 text-emerald-400 rounded-xl p-4 flex items-center space-x-3 shadow-md shadow-emerald-950/10">
            <i data-lucide="circle-check" class="w-5 h-5 flex-shrink-0"></i>
            <span class="text-sm font-medium">{{ session('message') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-950/30 border border-red-900/50 text-red-400 rounded-xl p-4 flex items-center space-x-3 shadow-md shadow-red-950/10">
            <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
            <span class="text-sm font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Match Header Card -->
    <div class="bg-zinc-900 border border-zinc-850 rounded-2xl p-5 md:p-6 shadow-xl relative overflow-hidden">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <a href="/tournaments/{{ $match->tournament->uuid }}/view" wire:navigate class="text-xs font-bold text-violet-400 hover:text-violet-300 transition-colors uppercase tracking-wider flex items-center space-x-1">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    <span>{{ $match->tournament->name }}</span>
                </a>
                <h1 class="text-2xl md:text-3xl font-black font-orbitron tracking-wider text-white mt-1.5 uppercase">
                    MATCH HUB
                </h1>
                <p class="text-xs text-zinc-500 mt-0.5">
                    Round {{ $match->round->round_number }} • Match #{{ $match->id }}
                </p>
            </div>
            
            <div class="flex-shrink-0">
                @php
                    $statusColors = [
                        'PENDING' => 'bg-zinc-800 text-zinc-400 border-zinc-700',
                        'READY' => 'bg-blue-950/30 text-blue-400 border-blue-900/40',
                        'IN_PROGRESS' => 'bg-violet-950/40 text-violet-300 border-violet-850/60 animate-pulse',
                        'RESULT_SUBMITTED' => 'bg-amber-950/30 text-amber-400 border-amber-900/40',
                        'DISPUTED' => 'bg-red-950/30 text-red-400 border-red-900/40 shadow-sm shadow-red-500/5',
                        'COMPLETED' => 'bg-emerald-950/30 text-emerald-400 border-emerald-900/40',
                        'FORFEITED' => 'bg-zinc-805/85 text-zinc-450 border-zinc-800',
                    ];
                    $colorClass = $statusColors[$match->status->value ?? $match->status] ?? 'bg-zinc-800 text-zinc-400 border-zinc-700';
                @endphp
                <span class="text-xs font-bold uppercase tracking-widest border rounded-full px-4 py-1.5 {{ $colorClass }}">
                    {{ str_replace('_', ' ', $match->status->value ?? $match->status) }}
                </span>
            </div>
        </div>
    </div>

    <!-- H2H Matchups Display -->
    <div class="bg-gradient-to-b from-zinc-900 to-zinc-950 border border-zinc-850 rounded-2xl p-6 md:p-10 shadow-xl relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-r from-violet-600/5 to-fuchsia-600/5 pointer-events-none"></div>

        <div class="grid grid-cols-1 md:grid-cols-7 items-center gap-6 md:gap-0 relative z-10">
            <!-- Player A info -->
            <div class="md:col-span-3 flex flex-col items-center md:items-end text-center md:text-right space-y-4">
                <div class="bg-zinc-950 p-4 rounded-full border-2 {{ $match->winner_registration_id === $match->player_a_registration_id && $match->winner_registration_id ? 'border-emerald-500 text-emerald-400 bg-emerald-950/10' : 'border-zinc-800 text-zinc-500' }} shadow-lg shadow-black/40">
                    <i data-lucide="user" class="w-12 h-12"></i>
                </div>
                <div class="space-y-1">
                    <h2 class="text-xl font-bold text-zinc-100 truncate w-60">
                        {{ $match->playerARegistration?->user?->profile?->display_name ?: $match->playerARegistration?->user?->username ?: 'TBD' }}
                    </h2>
                    <span class="block text-xs text-zinc-500 font-semibold">
                        @ @if($match->playerARegistration?->user?->username){{ $match->playerARegistration->user->username }}@else{{ 'To Be Determined' }}@endif
                    </span>
                    @if($match->winner_registration_id === $match->player_a_registration_id && $match->winner_registration_id)
                        <span class="inline-flex items-center space-x-1 text-xs font-bold text-emerald-400 uppercase tracking-wider bg-emerald-950/40 border border-emerald-900/60 rounded px-2.5 py-0.5 mt-2">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                            <span>Winner</span>
                        </span>
                    @endif
                </div>
            </div>

            <!-- VS Center column -->
            <div class="md:col-span-1 flex flex-col items-center justify-center py-4 md:py-0">
                <div class="font-orbitron font-black text-2xl md:text-4xl bg-gradient-to-r from-violet-400 via-fuchsia-400 to-indigo-400 bg-clip-text text-transparent">
                    VS
                </div>
            </div>

            <!-- Player B info -->
            <div class="md:col-span-3 flex flex-col items-center md:items-start text-center md:text-left space-y-4">
                <div class="bg-zinc-950 p-4 rounded-full border-2 {{ $match->winner_registration_id === $match->player_b_registration_id && $match->winner_registration_id ? 'border-emerald-500 text-emerald-400 bg-emerald-950/10' : 'border-zinc-800 text-zinc-500' }} shadow-lg shadow-black/40">
                    <i data-lucide="user" class="w-12 h-12"></i>
                </div>
                <div class="space-y-1">
                    <h2 class="text-xl font-bold text-zinc-100 truncate w-60">
                        {{ $match->playerBRegistration?->user?->profile?->display_name ?: $match->playerBRegistration?->user?->username ?: 'TBD' }}
                    </h2>
                    <span class="block text-xs text-zinc-500 font-semibold">
                        @ @if($match->playerBRegistration?->user?->username){{ $match->playerBRegistration->user->username }}@else{{ 'To Be Determined' }}@endif
                    </span>
                    @if($match->winner_registration_id === $match->player_b_registration_id && $match->winner_registration_id)
                        <span class="inline-flex items-center space-x-1 text-xs font-bold text-emerald-400 uppercase tracking-wider bg-emerald-950/40 border border-emerald-900/60 rounded px-2.5 py-0.5 mt-2">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                            <span>Winner</span>
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Match Participant Hub Actions -->
    @if($isParticipant || $isAdmin)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Admin Control (Only visible to Admin) -->
            @if($isAdmin)
                <div class="lg:col-span-2 bg-indigo-950/20 border border-indigo-500/30 rounded-2xl p-5 md:p-6 mb-4">
                    <div class="flex items-center space-x-2 border-b border-indigo-500/20 pb-3 mb-4">
                        <i data-lucide="shield-check" class="w-5 h-5 text-indigo-400"></i>
                        <h3 class="text-sm font-black font-orbitron tracking-widest text-indigo-100 uppercase">ADMIN OVERRIDE CONTROLS</h3>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <p class="text-xs text-indigo-300/80 mb-2 w-full">As an administrator, you can manually declare the winner and finalize this match instantly.</p>
                        @if($match->playerARegistration)
                            <button wire:click="adminCompleteMatch({{ $match->player_a_registration_id }})" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[10px] font-bold uppercase tracking-widest">Force Win: {{ $match->playerARegistration?->user?->username }}</button>
                        @endif
                        @if($match->playerBRegistration)
                            <button wire:click="adminCompleteMatch({{ $match->player_b_registration_id }})" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[10px] font-bold uppercase tracking-widest">Force Win: {{ $match->playerBRegistration?->user?->username }}</button>
                        @endif
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/20 text-indigo-400 border border-indigo-500/50 uppercase tracking-widest">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 mr-1.5 animate-pulse"></span>
                            ADMIN ACTIVE
                        </span>
                    </div>
                </div>
            @endif

            <!-- Result Submission or Dispute info -->
            <div class="bg-zinc-900 border border-zinc-850 rounded-2xl p-5 md:p-6 space-y-6">
                <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase border-b border-zinc-850 pb-3">
                    SUBMIT RESULTS
                </h2>

                @php
                    $statusVal = $match->status->value ?? $match->status;
                @endphp

                @if(in_array($statusVal, ['ready', 'in_progress']))
                    <form wire:submit.prevent="submitResult" class="space-y-4">
                        <!-- Select Winner -->
                        <div>
                            <span class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-2">Declare Winner</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <!-- Player A -->
                                @if($match->player_a_registration_id)
                                    <label class="flex items-center space-x-3 bg-zinc-950 border {{ $winnerRegistrationId === $match->player_a_registration_id ? 'border-violet-500' : 'border-zinc-800' }} rounded-xl p-3.5 cursor-pointer hover:border-zinc-700 transition-colors">
                                        <input wire:model="winnerRegistrationId" type="radio" value="{{ $match->player_a_registration_id }}" class="h-4 w-4 text-violet-600 border-zinc-800 focus:ring-violet-500 focus:ring-offset-zinc-900">
                                        <span class="text-sm font-semibold text-zinc-200">
                                            {{ $match->playerARegistration?->user?->username }} (Player A)
                                        </span>
                                    </label>
                                @endif
                                
                                <!-- Player B -->
                                @if($match->player_b_registration_id)
                                    <label class="flex items-center space-x-3 bg-zinc-950 border {{ $winnerRegistrationId === $match->player_b_registration_id ? 'border-violet-500' : 'border-zinc-800' }} rounded-xl p-3.5 cursor-pointer hover:border-zinc-700 transition-colors">
                                        <input wire:model="winnerRegistrationId" type="radio" value="{{ $match->player_b_registration_id }}" class="h-4 w-4 text-violet-600 border-zinc-800 focus:ring-violet-500 focus:ring-offset-zinc-900">
                                        <span class="text-sm font-semibold text-zinc-200">
                                            {{ $match->playerBRegistration?->user?->username }} (Player B)
                                        </span>
                                    </label>
                                @endif
                            </div>
                            @error('winnerRegistrationId') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Notes -->
                        <div>
                            <label for="notes" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider">Submission Notes (Optional)</label>
                            <textarea wire:model="notes" id="notes" rows="3"
                                class="mt-1.5 block w-full px-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-lg text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500 transition-all duration-200"
                                placeholder="E.g., Match won 2-1. Good game!"></textarea>
                            @error('notes') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Proof Upload -->
                        <div>
                            <label for="submissionProof" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-2">Upload Proof (Optional)</label>
                            <div class="bg-zinc-950 border border-zinc-800 rounded-lg p-4 text-center cursor-pointer relative hover:border-zinc-700 transition-colors">
                                <input wire:model="submissionProof" id="submissionProof" type="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                                <div class="space-y-1.5 text-zinc-400">
                                    <i data-lucide="upload-cloud" class="w-6 h-6 mx-auto text-zinc-500"></i>
                                    <p class="text-[10px] font-bold">
                                        {{ $submissionProof ? $submissionProof->getClientOriginalName() : 'Click to upload match screenshot' }}
                                    </p>
                                </div>
                            </div>
                            @error('submissionProof') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Submit Button -->
                        <div class="flex flex-col space-y-4 pt-2 border-t border-zinc-800/50" x-data="{
                            endTime: new Date('{{ ($match->started_at ?? now())->addMinutes($match->tournament->waiting_result_time)->toIso8601String() }}').getTime(),
                            timeLeft: 0,
                            init() {
                                this.updateTimer();
                                setInterval(() => this.updateTimer(), 1000);
                            },
                            updateTimer() {
                                const now = new Date().getTime();
                                const diff = this.endTime - now;
                                this.timeLeft = Math.max(0, Math.floor(diff / 1000));
                            },
                            formatTime(seconds) {
                                const m = Math.floor(seconds / 60);
                                const s = seconds % 60;
                                return `${m}m ${s}s`;
                            }
                        }">
                            <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center mb-2">
                                SUBMISSION DEADLINE: <span class="text-amber-400" x-text="formatTime(timeLeft)"></span>
                            </div>

                            <button type="submit" 
                                class="w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 transition-all duration-200 shadow-md shadow-violet-900/20 uppercase tracking-widest font-orbitron">
                                Submit Match Results
                            </button>
                            
                            <div class="space-y-3">
                                <div class="flex items-center space-x-2">
                                    <div class="h-px flex-grow bg-zinc-800"></div>
                                    <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-[0.2em]">Conflict / Dispute</span>
                                    <div class="h-px flex-grow bg-zinc-800"></div>
                                </div>
                                
                                <textarea wire:model="disputeReason" rows="1" class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-300 focus:outline-none focus:border-red-500/50" placeholder="Reason for dispute..."></textarea>
                                
                                <button type="button" wire:click="openDispute"
                                    class="w-full bg-red-950/20 border border-red-900/40 hover:border-red-750 text-red-400 hover:text-red-300 font-bold text-[10px] py-2 rounded-lg transition-colors duration-200 uppercase tracking-widest font-orbitron">
                                    Open Official Dispute
                                </button>
                            </div>
                        </div>
                    </form>
                @elseif($statusVal === 'waiting_for_confirmation' && !$isSubmitter)
                    <div class="space-y-4" x-data="{
                        endTime: new Date('{{ $match->result_submitted_at?->addMinutes($match->tournament->waiting_result_time)->toIso8601String() ?? now()->toIso8601String() }}').getTime(),
                        timeLeft: 0,
                        init() {
                            this.updateTimer();
                            setInterval(() => this.updateTimer(), 1000);
                        },
                        updateTimer() {
                            const now = new Date().getTime();
                            const diff = this.endTime - now;
                            this.timeLeft = Math.max(0, Math.floor(diff / 1000));
                        },
                        formatTime(seconds) {
                            const m = Math.floor(seconds / 60);
                            const s = seconds % 60;
                            return `${m}m ${s}s`;
                        }
                    }">
                        <p class="text-sm text-zinc-400">
                            Your opponent has submitted a match result. Please confirm it to finalize the match before time runs out: 
                            <span class="font-black text-amber-400" x-text="formatTime(timeLeft)"></span>.
                        </p>
                        
                        <div class="space-y-2">
                            <label for="disputeReason" class="block text-[10px] font-bold text-zinc-500 uppercase tracking-widest">WANT TO DISPUTE INSTEAD? (REASON REQUIRED)</label>
                            <textarea wire:model="disputeReason" id="disputeReason" rows="2" class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-4 py-2 text-xs text-zinc-200 focus:outline-none focus:border-red-500" placeholder="Describe the issue..."></textarea>
                            @error('disputeReason') <span class="text-[10px] text-red-500 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center space-x-4">
                            <button type="button" wire:click="confirmResult"
                                class="flex-grow flex justify-center py-2.5 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 transition-all duration-200">
                                Confirm Result
                            </button>
                            <button type="button" wire:click="openDispute"
                                class="bg-red-950/20 border border-red-900/60 hover:border-red-750 text-red-400 hover:text-red-300 font-bold text-sm py-2.5 px-4 rounded-lg transition-colors duration-200">
                                Open Dispute
                            </button>
                        </div>
                    </div>
                @else
                    <div class="bg-zinc-950/40 border border-zinc-800 rounded-xl p-6 text-center text-zinc-500">
                        <i data-lucide="lock" class="w-6 h-6 mx-auto text-zinc-650 mb-2"></i>
                        <p class="text-xs font-semibold">Results can only be submitted while the match status is READY or IN PROGRESS.</p>
                    </div>
                @endif
            </div>

            <!-- Dispute & Evidence Upload Panel -->
            <div class="bg-zinc-900 border border-zinc-850 rounded-2xl p-5 md:p-6 space-y-6">
                <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase border-b border-zinc-850 pb-3">
                    DISPUTE MANAGER
                </h2>

                @if($activeDispute)
                    <div class="space-y-4">
                        <div class="bg-red-950/25 border border-red-900/40 rounded-xl p-4 space-y-2 text-red-400">
                            <div class="flex items-center space-x-2 font-bold text-sm">
                                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                <span>Active Dispute Logged</span>
                            </div>
                            <p class="text-xs text-red-400/80 leading-relaxed">
                                A dispute has been opened for this match. Please submit screenshot evidence (PNG, JPG, or WEBP, max 2MB) immediately for admins to review and declare the correct bracket winner.
                            </p>
                        </div>

                        <!-- Evidence Form -->
                        <form wire:submit.prevent="submitEvidence" class="space-y-4">
                            <div>
                                <label for="evidenceFile" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-2">Upload Evidence File</label>
                                <div class="bg-zinc-950 border border-zinc-800 rounded-lg p-4 text-center cursor-pointer relative hover:border-zinc-700 transition-colors">
                                    <input wire:model="evidenceFile" id="evidenceFile" type="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                                    <div class="space-y-1.5 text-zinc-400">
                                        <i data-lucide="upload-cloud" class="w-8 h-8 mx-auto text-zinc-500"></i>
                                        <p class="text-xs font-bold">
                                            {{ $evidenceFile ? $evidenceFile->getClientOriginalName() : 'Click or drag file to upload' }}
                                        </p>
                                        <p class="text-[10px] text-zinc-600">
                                            Images only: PNG, JPG, WEBP &mdash; max 2MB.
                                        </p>
                                    </div>
                                </div>
                                @error('evidenceFile') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <button type="submit" 
                                class="w-full flex justify-center py-2.5 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 transition-all duration-200 shadow-md shadow-red-900/20">
                                Submit Evidence File
                            </button>
                        </form>
                    </div>
                @else
                    <div class="bg-zinc-950/40 border border-zinc-800 rounded-xl p-6 text-center text-zinc-500">
                        <i data-lucide="shield-check" class="w-6 h-6 mx-auto text-zinc-650 mb-2"></i>
                        <p class="text-xs font-semibold">There is no active dispute logged for this match.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Results Submissions log -->
    <div class="bg-zinc-900 border border-zinc-850 rounded-2xl p-5 md:p-6 space-y-4">
        <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase border-b border-zinc-850 pb-3">
            SUBMISSIONS LOG
        </h2>

        @if($match->resultSubmissions->count() > 0)
            <div class="space-y-4">
                @foreach($match->resultSubmissions as $sub)
                    <div class="bg-zinc-950 border border-zinc-850 rounded-xl p-4 flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <span class="block text-xs font-bold text-zinc-300">
                                Submitted by: {{ $sub->user?->username }}
                            </span>
                            @if($sub->notes)
                                <p class="text-xs text-zinc-500 italic leading-relaxed">
                                    "{{ $sub->notes }}"
                                </p>
                            @endif
                            @if($sub->proof_path)
                                <div class="mt-2">
                                    <a href="/storage/{{ $sub->proof_path }}" target="_blank" class="inline-flex items-center space-x-1.5 text-[10px] font-bold text-violet-400 hover:text-violet-300 transition-colors uppercase tracking-wider">
                                        <i data-lucide="image" class="w-3 h-3"></i>
                                        <span>View Proof</span>
                                    </a>
                                </div>
                            @endif
                        </div>
                        <span class="text-[10px] text-zinc-500 font-semibold uppercase tracking-wider">
                            {{ $sub->created_at?->diffForHumans() }}
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-6 text-xs text-zinc-650 font-semibold">
                No result submissions logged yet.
            </div>
        @endif
    </div>

    <!-- Active Disputes & Evidence files -->
    <div class="bg-zinc-900 border border-zinc-850 rounded-2xl p-5 md:p-6 space-y-4">
        <h2 class="text-lg font-bold font-orbitron tracking-wide text-zinc-100 uppercase border-b border-zinc-850 pb-3">
            DISPUTES & EVIDENCE FILES
        </h2>

        @if($match->disputes->count() > 0)
            <div class="space-y-6">
                @foreach($match->disputes as $disp)
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-zinc-300">
                                Dispute #{{ $disp->id }} (Opened: {{ $disp->created_at?->format('M d, Y') }})
                            </span>
                            <span class="text-[10px] font-bold uppercase tracking-wider border rounded px-2 py-0.5 {{ $disp->status->value === 'RESOLVED' ? 'bg-zinc-800 text-zinc-400 border-zinc-800' : 'bg-red-950/30 text-red-400 border-red-900/40' }}">
                                {{ $disp->status->value }}
                            </span>
                        </div>
                        
                        <!-- Evidence Files -->
                        @if($disp->evidence->count() > 0)
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 pl-4">
                                @foreach($disp->evidence as $ev)
                                    <div class="bg-zinc-950 border border-zinc-850 rounded-xl p-3 flex items-center space-x-2.5">
                                        <i data-lucide="image" class="w-4 h-4 text-violet-400"></i>
                                        <div class="truncate">
                                            <!-- clickable absolute storage path link -->
                                            <a href="/storage/{{ $ev->file_path }}" target="_blank" class="block text-xs font-semibold text-zinc-300 hover:text-violet-400 transition-colors truncate">
                                                Evidence File #{{ $ev->id }}
                                            </a>
                                            <span class="block text-[9px] text-zinc-600">
                                                Uploaded by: {{ $ev->uploader?->username ?? 'Player' }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-[10px] text-zinc-600 italic pl-4">No evidence files uploaded yet for this dispute.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-6 text-xs text-zinc-650 font-semibold">
                No disputes logged for this match.
            </div>
        @endif
    </div>
</div>
