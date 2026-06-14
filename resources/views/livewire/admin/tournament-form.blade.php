<div x-data="{ 
    step: @entangle('step').live,
    totalSteps: 4,
    isValidating: false,
    
    // Auto-save/Load Draft Logic
    init() {
        if (!this.$wire.isEditMode) {
            const draft = localStorage.getItem('tournament_draft');
            if (draft) {
                const data = JSON.parse(draft);
                // Simple mapping for non-rich text fields
                $wire.name = data.name || '';
                $wire.game_id = data.game_id || 0;
                $wire.platform_id = data.platform_id || 0;
                $wire.frequency = data.frequency || 'one-time';
                $wire.team_size = data.team_size || 1;
                // Rich text will be handled by their respective components
            }
        }
    },

    saveDraft() {
        if (this.$wire.isEditMode) return;
        const data = {
            name: $wire.name,
            game_id: $wire.game_id,
            platform_id: $wire.platform_id,
            frequency: $wire.frequency,
            team_size: $wire.team_size,
            description: $wire.description,
            rules: $wire.rules
        };
        localStorage.setItem('tournament_draft', JSON.stringify(data));
    },

    clearDraft() {
        localStorage.removeItem('tournament_draft');
    },

    get isStepReady() {
        if (this.step === 1) {
            return $wire.name.trim().length > 0 && $wire.game_id > 0;
        }
        if (this.step === 2) {
            return $wire.platform_id && $wire.platform_id > 0 && $wire.frequency && $wire.team_size >= 1;
        }
        if (this.step === 3) {
            return $wire.registration_open_at && 
                   $wire.registration_close_at && 
                   $wire.checkin_open_at && 
                   $wire.checkin_close_at && 
                   $wire.start_at;
        }
        if (this.step === 4) {
            return $wire.entry_fee !== '' && $wire.prize_pool !== '';
        }
        return true;
    },

    async nextStep() { 
        if (this.step === 1) {
            window.dispatchEvent(new CustomEvent('sync-quill'));
            // Small delay to ensure Livewire catches the synced data
            await new Promise(r => setTimeout(r, 100));
        }

        this.saveDraft(); // Save progress locally

        this.isValidating = true;
        try {
            await $wire.validateStep(this.step);
            if(this.step < this.totalSteps) this.step++;
            this.isValidating = false;
        } catch (e) {
            this.isValidating = false;
        }
    },
    prevStep() { if(this.step > 1) this.step-- }
}">
    <!-- Step title and description updated -->

    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-xl font-bold text-white">{{ $isEditMode ? 'Edit Tournament' : 'Create New Tournament' }}</h2>
            <p class="text-sm text-slate-400 mt-1">Step <span x-text="step"></span> of <span x-text="totalSteps"></span>: 
                <span x-show="step === 1">Identity & Content</span>
                <span x-show="step === 2">Tournament Settings</span>
                <span x-show="step === 3">Schedule & Logistics</span>
                <span x-show="step === 4">Stakes, Prizes & Capacity</span>
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.tournaments') }}" wire:navigate class="bg-slate-800 hover:bg-slate-700 text-white font-semibold text-sm px-4 py-2.5 rounded-lg flex items-center transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                <span>Exit</span>
            </a>
        </div>
    </div>

    <!-- Wizard Progress Bar -->
    <div class="max-w-4xl mx-auto mb-8">
        <div class="relative">
            <div class="overflow-hidden h-1.5 mb-4 text-xs flex rounded bg-slate-800">
                <div :style="'width: ' + (step / totalSteps * 100) + '%'" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-500 transition-all duration-500"></div>
            </div>
            <div class="flex justify-between text-[10px] font-bold uppercase tracking-widest text-slate-500">
                <span :class="step >= 1 ? 'text-indigo-400' : ''">Identity</span>
                <span :class="step >= 2 ? 'text-indigo-400' : ''">Settings</span>
                <span :class="step >= 3 ? 'text-indigo-400' : ''">Schedule</span>
                <span :class="step >= 4 ? 'text-indigo-400' : ''">Prizes</span>
            </div>
        </div>
    </div>

    @if ($isLocked)
        <div class="bg-amber-900/40 border border-amber-500/50 text-amber-200 px-4 py-3 rounded-lg mb-6 flex items-start max-w-4xl mx-auto">
            <i data-lucide="lock" class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0"></i>
            <div>
                <p class="text-sm font-bold uppercase tracking-wide">Limited Edit Mode Active</p>
                <p class="text-xs mt-1 text-amber-200/80">Critical fields like fees, prizes, and team configuration are locked. You can still update the description, rules, banner, and schedule.</p>
            </div>
        </div>
    @endif

    <!-- Feedback Alerts -->
    @if (session()->has('success'))
        <div class="bg-emerald-900/50 border border-emerald-500/50 text-emerald-400 px-4 py-3 rounded-lg mb-6 flex items-start max-w-4xl mx-auto">
            <i data-lucide="check-circle-2" class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0"></i>
            <p class="text-sm font-medium">{{ session('success') }}</p>
        </div>
    @endif

    <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-2xl relative z-10 max-w-4xl mx-auto">
        <form wire:submit.prevent="saveTournament">
            
            <!-- STEP 1: Identity & Content -->
            <div x-show="step === 1" class="p-6 space-y-6" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Tournament Name <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="name" placeholder="e.g. Pro League Summer 2026" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('name') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1 flex items-center">
                                <span>Game <span class="text-red-500">*</span></span>
                                @if($isLocked) <i data-lucide="lock" class="w-2.5 h-2.5 ml-1 text-slate-500"></i> @endif
                            </label>
                            <select wire:model="game_id" @disabled($isLocked) class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                <option value="">Select Game</option>
                                @foreach($games as $game)
                                    <option value="{{ $game->id }}">{{ $game->translations->first()?->name ?? $game->slug }}</option>
                                @endforeach
                            </select>
                            @error('game_id') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Banner Image (Optional)</label>
                            <input type="file" wire:model="banner" accept="image/*" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @if ($banner)
                                <div class="mt-2 text-xs text-indigo-400">File selected: {{ $banner->getClientOriginalName() }}</div>
                            @endif
                            @error('banner') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div wire:ignore 
                             x-data="{ 
                                quill: null 
                             }" 
                             @sync-quill.window="$wire.description = quill.root.innerHTML"
                             x-init="
                                quill = new Quill($refs.editor, {
                                    theme: 'snow',
                                    placeholder: 'Write a compelling description...',
                                    modules: {
                                        toolbar: [['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['clean']]
                                    }
                                });
                                quill.root.innerHTML = $wire.description;
                             ">
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Description <span class="text-red-500">*</span></label>
                            <div class="bg-slate-900 border border-slate-800 rounded-lg overflow-hidden">
                                <div x-ref="editor" class="text-slate-100 min-h-[120px] border-none ql-custom-dark"></div>
                            </div>
                            @error('description') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div wire:ignore 
                             x-data="{ 
                                quill: null 
                             }" 
                             @sync-quill.window="$wire.rules = quill.root.innerHTML"
                             x-init="
                                quill = new Quill($refs.editor, {
                                    theme: 'snow',
                                    placeholder: 'Define tournament rules...',
                                    modules: {
                                        toolbar: [['bold', 'italic'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['clean']]
                                    }
                                });
                                quill.root.innerHTML = $wire.rules;
                             ">
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1 flex justify-between">
                                <span>Rules <span class="text-red-500">*</span></span>
                            </label>
                            <div class="bg-slate-900 border border-slate-800 rounded-lg overflow-hidden">
                                <div x-ref="editor" class="text-slate-100 min-h-[120px] border-none ql-custom-dark"></div>
                            </div>
                            @error('rules') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Tournament Settings -->
            <div x-show="step === 2" class="p-6 space-y-6" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1 flex items-center">
                                    <span>Platform <span class="text-red-500">*</span></span>
                                    @if($isLocked) <i data-lucide="lock" class="w-2.5 h-2.5 ml-1 text-slate-500"></i> @endif
                                </label>
                                <select wire:model="platform_id" @disabled($isLocked) class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <option value="">No Specific Platform</option>
                                    @foreach($platforms as $plat)
                                        <option value="{{ $plat->id }}">{{ $plat->name }}</option>
                                    @endforeach
                                </select>
                                @error('platform_id') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1 flex items-center">
                                    <span>Frequency <span class="text-red-500">*</span></span>
                                    @if($isLocked) <i data-lucide="lock" class="w-2.5 h-2.5 ml-1 text-slate-500"></i> @endif
                                </label>
                                <select wire:model="frequency" @disabled($isLocked) class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <option value="one-time">One-time / Single Event</option>
                                    <option value="daily">Daily Recurring</option>
                                    <option value="weekly">Weekly Recurring</option>
                                    <option value="monthly">Monthly Recurring</option>
                                </select>
                                @error('frequency') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1 flex items-center">
                                    <span>Team Size <span class="text-red-500">*</span></span>
                                    @if($isLocked) <i data-lucide="lock" class="w-2.5 h-2.5 ml-1 text-slate-500"></i> @endif
                                </label>
                                <input type="number" wire:model="team_size" @disabled($isLocked) min="1" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                <p class="text-[9px] text-slate-500 mt-1 italic">Individual players per team (1 = Solo, 2 = Duo)</p>
                                @error('team_size') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1 flex items-center">
                                    <span>Winning Points</span>
                                    @if($isLocked) <i data-lucide="lock" class="w-2.5 h-2.5 ml-1 text-slate-500"></i> @endif
                                </label>
                                <input type="number" wire:model="winning_points" @disabled($isLocked) class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                @error('winning_points') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="bg-indigo-900/10 border border-indigo-500/20 p-4 rounded-lg">
                            <h4 class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-4 flex items-center">
                                <i data-lucide="clock" class="w-4 h-4 mr-2"></i> Match Wait Timings
                            </h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Acceptance Wait (m)</label>
                                    <input type="number" wire:model="waiting_time" placeholder="15" class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                    @error('waiting_time') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Result Wait (m)</label>
                                    <input type="number" wire:model="waiting_result_time" placeholder="30" class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                    @error('waiting_result_time') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-500 mt-3 leading-relaxed">
                                <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                                Acceptance wait is the time players have to join a match after it's ready. Result wait is the time allowed to submit scores.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Schedule & Logistics -->
            <div x-show="step === 3" class="p-6 space-y-6" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-6">
                        <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center">
                            <i data-lucide="calendar" class="w-4 h-4 mr-2 text-indigo-400"></i> Registration Window <span class="text-red-500 ml-1">*</span>
                        </h4>
                        <p class="text-[9px] text-slate-500 mb-4 italic">Registration must close before check-in can begin.</p>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Opens At <span class="text-red-500">*</span></label>
                                <input type="datetime-local" wire:model="registration_open_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('registration_open_at') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Closes At <span class="text-red-500">*</span></label>
                                <input type="datetime-local" wire:model="registration_close_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('registration_close_at') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center pt-4">
                            <i data-lucide="user-check" class="w-4 h-4 mr-2 text-indigo-400"></i> Check-in Window <span class="text-red-500 ml-1">*</span>
                        </h4>
                        <p class="text-[9px] text-slate-500 mb-4 italic">Check-in must start after registration ends and close before the tournament begins.</p>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Opens At <span class="text-red-500">*</span></label>
                                <input type="datetime-local" wire:model="checkin_open_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('checkin_open_at') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Closes At <span class="text-red-500">*</span></label>
                                <input type="datetime-local" wire:model="checkin_close_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('checkin_close_at') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6 flex flex-col justify-center">
                        <div class="bg-indigo-600/10 border border-indigo-500/20 p-6 rounded-2xl flex flex-col items-center text-center">
                            <div class="w-12 h-12 bg-indigo-600 rounded-full flex items-center justify-center mb-4 shadow-lg shadow-indigo-500/20">
                                <i data-lucide="play" class="w-6 h-6 text-white fill-current"></i>
                            </div>
                            <h4 class="text-sm font-bold text-slate-200 uppercase tracking-widest mb-2">Tournament Start <span class="text-red-500">*</span></h4>
                            <p class="text-[10px] text-slate-500 mb-4 max-w-[240px]">This is when the first matches are generated. Must be after the check-in window ends.</p>
                            
                            <input type="datetime-local" wire:model="start_at" class="w-full max-w-xs bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-sm text-slate-100 text-center font-bold focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
                            @error('start_at') <span class="text-red-400 text-[10px] mt-2 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 4: Stakes, Prizes & Capacity -->
            <div x-show="step === 4" class="p-6 space-y-6" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Left: Stakes & Capacity -->
                    <div class="space-y-6">
                        <div class="bg-slate-900/50 p-5 rounded-xl border border-slate-800 space-y-5">
                            <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Entry & Capacity</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 flex items-center">
                                        <span>Entry Fee ($)</span>
                                        @if($isLocked) <i data-lucide="lock" class="w-2.5 h-2.5 ml-1 text-slate-500"></i> @endif
                                    </label>
                                    <input type="text" wire:model="entry_fee" @disabled($isLocked) class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 disabled:opacity-50">
                                    @error('entry_fee') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div></div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 flex items-center">
                                        <span>Min Players</span>
                                        @if($isLocked) <i data-lucide="lock" class="w-2.5 h-2.5 ml-1 text-slate-500"></i> @endif
                                    </label>
                                    <input type="number" wire:model="min_participants" @disabled($isLocked) class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 disabled:opacity-50">
                                    @error('min_participants') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 flex items-center">
                                        <span>Max Players <span class="text-red-500">*</span></span>
                                        @if($isLocked) <i data-lucide="lock" class="w-2.5 h-2.5 ml-1 text-slate-500"></i> @endif
                                    </label>
                                    <input type="number" wire:model="max_participants" @disabled($isLocked) class="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 disabled:opacity-50">
                                    <p class="text-[9px] text-slate-600 mt-1 italic">Total player capacity for the whole tournament</p>
                                    @error('max_participants') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Prize Pool Breakdown -->
                    <div class="space-y-6">
                        <div class="bg-emerald-900/10 p-5 rounded-xl border border-emerald-500/20 space-y-5">
                            <h4 class="text-xs font-bold text-emerald-400 uppercase tracking-wider mb-2 flex items-center">
                                <i data-lucide="trophy" class="w-4 h-4 mr-2"></i> Prizes & Rewards
                            </h4>
                            <div>
                                <label class="block text-[10px] font-bold text-emerald-500/70 uppercase mb-1 flex items-center">
                                    <span>Total Prize Pool ($)</span>
                                    @if($isLocked) <i data-lucide="lock" class="w-2.5 h-2.5 ml-1 text-emerald-500/50"></i> @endif
                                </label>
                                <input type="text" wire:model="prize_pool" @disabled($isLocked) placeholder="0.00" class="w-full bg-slate-950 border border-emerald-500/20 rounded px-4 py-3 text-lg font-black text-emerald-400 focus:outline-none focus:border-emerald-500 disabled:opacity-50 transition-all">
                                @error('prize_pool') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="grid grid-cols-3 gap-3 pt-2">
                                <div>
                                    <label class="block text-[9px] font-bold text-slate-500 uppercase mb-1">1st Place</label>
                                    <input type="text" wire:model="prize_1st" @disabled($isLocked) placeholder="0.00" class="w-full bg-slate-950 border border-slate-800 rounded px-2 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 disabled:opacity-50">
                                </div>
                                <div>
                                    <label class="block text-[9px] font-bold text-slate-500 uppercase mb-1">2nd Place</label>
                                    <input type="text" wire:model="prize_2nd" @disabled($isLocked) placeholder="0.00" class="w-full bg-slate-950 border border-slate-800 rounded px-2 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 disabled:opacity-50">
                                </div>
                                <div>
                                    <label class="block text-[9px] font-bold text-slate-500 uppercase mb-1">3rd Place</label>
                                    <input type="text" wire:model="prize_3rd" @disabled($isLocked) placeholder="0.00" class="w-full bg-slate-950 border border-slate-800 rounded px-2 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 disabled:opacity-50">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wizard Controls -->
            <div class="px-6 py-4 bg-[#0b0f19] border-t border-slate-800 flex justify-between items-center">
                <button type="button" x-show="step > 1" @click="prevStep()" class="text-slate-400 hover:text-white font-bold text-xs uppercase flex items-center transition-colors">
                    <i data-lucide="chevron-left" class="w-4 h-4 mr-1"></i>
                    Back
                </button>
                <div x-show="step === 1"></div> <!-- Spacer -->

                <div class="flex space-x-3">
                    <button type="button" x-show="step < totalSteps" @click="nextStep()" 
                            :disabled="isValidating || !isStepReady"
                            wire:loading.attr="disabled"
                            wire:target="validateStep"
                            class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-6 py-2.5 rounded-lg flex items-center shadow-lg shadow-indigo-500/20 transition-all disabled:opacity-30 disabled:cursor-not-allowed">
                        <span x-show="!isValidating">Next Step</span>
                        <span x-show="isValidating">Validating...</span>
                        <i x-show="!isValidating" data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                        <div x-show="isValidating" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin ml-2"></div>
                    </button>

                    <button type="submit" x-show="step === totalSteps" @click="clearDraft()" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase px-8 py-2.5 rounded-lg flex items-center shadow-lg shadow-emerald-500/20 transition-all">
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                        {{ $isEditMode ? 'Save Changes' : 'Create Tournament' }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Custom CSS for Quill styling in dark mode -->
    <style>
        .ql-custom-dark .ql-editor {
            color: #f1f5f9 !important;
            min-height: 150px;
        }
        .ql-toolbar.ql-snow {
            border-color: #1e293b !important;
            background: #0f172a !important;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }
        .ql-container.ql-snow {
            border-color: #1e293b !important;
            background: #0b0f19 !important;
            border-bottom-left-radius: 0.5rem;
            border-bottom-right-radius: 0.5rem;
        }
        .ql-snow .ql-stroke {
            stroke: #94a3b8 !important;
        }
        .ql-snow .ql-fill {
            fill: #94a3b8 !important;
        }
        .ql-snow .ql-picker {
            color: #94a3b8 !important;
        }
        .ql-editor.ql-blank::before {
            color: #475569 !important;
            font-style: normal !important;
        }
        .ql-editor {
            font-family: inherit !important;
            font-size: 0.875rem !important;
        }
    </style>
</div>
