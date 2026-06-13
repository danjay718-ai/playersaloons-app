<div>
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-xl font-bold text-white">{{ $isEditMode ? 'Edit Tournament' : 'Create New Tournament' }}</h2>
            <p class="text-sm text-slate-400 mt-1">Fill in the details below to configure your tournament.</p>
        </div>
        <a href="{{ route('admin.tournaments') }}" wire:navigate class="bg-slate-800 hover:bg-slate-700 text-white font-semibold text-sm px-4 py-2.5 rounded-lg flex items-center transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
            <span>Back to Tournaments</span>
        </a>
    </div>

    <!-- Feedback Alerts -->
    @if (session()->has('success'))
        <div class="bg-emerald-900/50 border border-emerald-500/50 text-emerald-400 px-4 py-3 rounded-lg mb-6 flex items-start">
            <i data-lucide="check-circle-2" class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0"></i>
            <div>
                <p class="text-sm font-medium">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-900/50 border border-red-500/50 text-red-400 px-4 py-3 rounded-lg mb-6 flex items-start">
            <i data-lucide="alert-circle" class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0"></i>
            <div>
                <p class="text-sm font-medium">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-2xl relative z-10 max-w-4xl mx-auto">
        <form wire:submit.prevent="saveTournament" class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Tournament Name</label>
                        <input type="text" wire:model="name" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        @error('name') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Game</label>
                            <select wire:model="game_id" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                                <option value="">Select Game</option>
                                @foreach($games as $game)
                                    <option value="{{ $game->id }}">{{ $game->translations->first()?->name ?? $game->slug }}</option>
                                @endforeach
                            </select>
                            @error('game_id') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Entry Fee ($)</label>
                            <input type="text" wire:model="entry_fee" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('entry_fee') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Banner Image (Optional)</label>
                        <input type="file" wire:model="banner" accept="image/*" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        @if ($banner)
                            <div class="mt-2 text-xs text-indigo-400">File selected</div>
                        @endif
                        @error('banner') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Description</label>
                        <textarea wire:model="description" rows="4" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500"></textarea>
                        @error('description') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1 flex justify-between">
                            <span>Rules</span>
                            <span class="text-indigo-400 normal-case font-normal text-[10px]">Pre-filled with default template</span>
                        </label>
                        <textarea wire:model="rules" rows="6" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500"></textarea>
                        @error('rules') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Platform</label>
                            <select wire:model="platform_id" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                                <option value="">Select Platform</option>
                                @foreach($platforms as $plat)
                                    <option value="{{ $plat->id }}">{{ $plat->name }}</option>
                                @endforeach
                            </select>
                            @error('platform_id') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Frequency</label>
                            <select wire:model="frequency" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                            @error('frequency') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Team Size</label>
                            <input type="number" wire:model="team_size" min="1" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('team_size') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Winning Pts</label>
                            <input type="number" wire:model="winning_points" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('winning_points') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Min Players</label>
                            <input type="number" wire:model="min_participants" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('min_participants') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Max Players</label>
                            <input type="number" wire:model="max_participants" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            @error('max_participants') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="bg-slate-900/50 p-4 rounded-lg border border-slate-800 space-y-4">
                        <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Prizes</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Prize Pool ($)</label>
                                <input type="text" wire:model="prize_pool" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('prize_pool') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">1st Prize ($)</label>
                                <input type="text" wire:model="prize_1st" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('prize_1st') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">2nd Prize ($)</label>
                                <input type="text" wire:model="prize_2nd" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('prize_2nd') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">3rd Prize ($)</label>
                                <input type="text" wire:model="prize_3rd" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('prize_3rd') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900/50 p-4 rounded-lg border border-slate-800 space-y-4">
                        <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Schedule & Timings</h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Reg Opens</label>
                                <input type="datetime-local" wire:model="registration_open_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('registration_open_at') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Reg Closes</label>
                                <input type="datetime-local" wire:model="registration_close_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('registration_close_at') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Check-in Opens</label>
                                <input type="datetime-local" wire:model="checkin_open_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('checkin_open_at') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Check-in Closes</label>
                                <input type="datetime-local" wire:model="checkin_close_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('checkin_close_at') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4 mt-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Start Time</label>
                                <input type="datetime-local" wire:model="start_at" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('start_at') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Wait Time (m)</label>
                                <input type="number" wire:model="waiting_time" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('waiting_time') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Result Time (m)</label>
                                <input type="number" wire:model="waiting_result_time" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                @error('waiting_result_time') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-6 border-t border-slate-800 flex justify-end space-x-3">
                <a href="{{ route('admin.tournaments') }}" wire:navigate class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-sm px-6 py-3 rounded-lg transition-colors">
                    Cancel
                </a>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm px-6 py-3 rounded-lg shadow-[0_4px_12px_rgba(79,70,229,0.2)] transition-colors">
                    {{ $isEditMode ? 'Update Tournament' : 'Create Tournament' }}
                </button>
            </div>
        </form>
    </div>
</div>
