<div
    x-data="{
        step: @entangle('step').live,
        totalSteps: 5,
        busy: false,
        validationTick: 0,
        editorValidity: { description: false, rules: false },
        isStepComplete(stepNumber) {
            this.validationTick;
            if (stepNumber === 2) {
                return this.editorValidity.description && this.editorValidity.rules;
            }

            const section = this.$refs.form?.querySelector(`[data-step='${stepNumber}']`);
            if (!section) return false;

            return [...section.querySelectorAll('input, select, textarea')]
                .filter(field => !field.disabled)
                .every(field => {
                    if (field.dataset.invalidZero === 'true' && Number(field.value) <= 0) return false;
                    return (!field.required && field.value === '') || field.checkValidity();
                });
        },
        async next() {
            if (!this.isStepComplete(this.step)) return;
            window.dispatchEvent(new CustomEvent('sync-tournament-editors'));
            await new Promise(resolve => setTimeout(resolve, 75));
            this.busy = true;
            try {
                await $wire.validateStep(this.step);
                if (this.step < this.totalSteps) this.step++;
            } finally { this.busy = false; }
        },
        previous() { if (this.step > 1) this.step--; },
        async save() {
            window.dispatchEvent(new CustomEvent('sync-tournament-editors'));
            await new Promise(resolve => setTimeout(resolve, 75));
            this.busy = true;
            try { await $wire.saveTournament(); } finally { this.busy = false; }
        }
    }"
    @input="validationTick++"
    @change="validationTick++"
    @tournament-editor-validity.window="editorValidity[$event.detail.field] = $event.detail.valid"
    class="w-full"
>
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-white">{{ $isEditMode ? 'Edit Tournament' : 'Create Tournament' }}</h1>
            <p class="mt-1 text-sm text-slate-400">
                <span x-text="`Step ${step} of ${totalSteps}`"></span><span class="mx-2 text-slate-700">•</span>
                <span x-show="step === 1">Tournament Details</span><span x-show="step === 2">Description & Rules</span>
                <span x-show="step === 3">Players & Match Settings</span><span x-show="step === 4">Dates & Timezone</span>
                <span x-show="step === 5">Fees & Prizes</span>
            </p>
        </div>
        <a href="{{ route('admin.tournaments') }}" wire:navigate class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-4 py-2.5 text-sm font-bold text-slate-200 hover:bg-slate-800">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Exit
        </a>
    </div>

    @if ($isLocked)
        <div class="mb-5 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200">
            Core tournament fields are locked because registration has already started. Schedule, content, timezone, and live stream links may still be updated.
        </div>
    @endif

    <div class="mb-6 grid grid-cols-5 gap-2">
        @foreach (['Details', 'Content', 'Match Setup', 'Schedule', 'Prizes'] as $number => $label)
            <button type="button" @click="if ({{ $number + 1 }} < step) step = {{ $number + 1 }}" class="group text-left">
                <span class="mb-2 block h-1.5 rounded-full transition" :class="step >= {{ $number + 1 }} ? 'bg-indigo-500' : 'bg-slate-800'"></span>
                <span class="hidden text-[10px] font-bold uppercase tracking-wider sm:block" :class="step >= {{ $number + 1 }} ? 'text-indigo-300' : 'text-slate-600'">{{ $label }}</span>
            </button>
        @endforeach
    </div>

    <form x-ref="form" x-on:submit.prevent="save" class="w-full overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70 shadow-2xl">
        <section data-step="1" x-show="step === 1" x-cloak class="p-5 md:p-8">
            <div class="mb-6"><h2 class="text-lg font-black text-white">Tournament Details</h2><p class="mt-1 text-sm text-slate-500">Choose the game, platform, and schedule frequency players will see.</p></div>
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">
                <div class="xl:col-span-2"><label class="field-label">Tournament Name *</label><input wire:model="name" type="text" required maxlength="255" placeholder="e.g. Summer Championship" class="form-field">@error('name') <p class="field-error">{{ $message }}</p> @enderror</div>
                <div><label class="field-label">Type *</label><select wire:model.live="competition_type" required @disabled($isLocked) class="form-field"><option value="tournament">Tournament</option><option value="head_to_head">Head-to-Head</option></select>@error('competition_type') <p class="field-error">{{ $message }}</p> @enderror</div>
                <div><label class="field-label">Game *</label><select wire:model.live="game_id" required data-invalid-zero="true" @disabled($isLocked) class="form-field"><option value="0">Select a game</option>@foreach($games as $game)<option value="{{ $game->id }}">{{ $game->translations->first()?->name ?? $game->slug }}</option>@endforeach</select>@error('game_id') <p class="field-error">{{ $message }}</p> @enderror</div>
                <div><label class="field-label">Platform *</label><select wire:model="platform_id" required @disabled($isLocked) class="form-field"><option value="">Select a platform</option>@foreach($platforms as $platform)<option value="{{ $platform->id }}">{{ $platform->name }}</option>@endforeach</select>@error('platform_id') <p class="field-error">{{ $message }}</p> @enderror</div>
                <div><label class="field-label">Frequency *</label><select wire:model="frequency" required @disabled($isLocked) class="form-field"><option value="one-time">One Time</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select>@error('frequency') <p class="field-error">{{ $message }}</p> @enderror</div>
                <div class="lg:col-span-2"><x-forms.image-crop-upload model="banner" label="Tournament Banner (Optional)" :width="960" :height="540" :max-mb="2" /></div>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 p-4"><input wire:model="is_featured" type="checkbox" class="mt-0.5 rounded border-slate-700 bg-slate-900 text-amber-500"><span><span class="block text-xs font-black uppercase text-amber-300">Featured Tournament</span><span class="mt-1 block text-xs leading-relaxed text-slate-500">Display this in the Featured section of the game page.</span></span></label>
            </div>
        </section>

        <section data-step="2" x-show="step === 2" x-cloak class="space-y-6 p-5 md:p-8">
            <div><h2 class="text-lg font-black text-white">Description & Rules</h2><p class="mt-1 text-sm text-slate-500">Each editor scrolls inside the card so the whole wizard stays compact.</p></div>
            @foreach ([['description', 'Description', 'Explain the tournament format and what players can expect.'], ['rules', 'Tournament Rules', 'The general PlayerSaloons rules are prefilled and can be adjusted.']] as [$property, $label, $placeholder])
                <div wire:ignore x-data="{ editor: null }" @sync-tournament-editors.window="if (editor) $wire.{{ $property }} = editor.root.innerHTML" x-init="
                    const boot = () => {
                        if (editor) return;
                        if (typeof window.Quill === 'undefined') { setTimeout(boot, 75); return; }
                        editor = new Quill($refs.editor, { theme: 'snow', placeholder: @js($placeholder), modules: { toolbar: [['bold','italic','underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']] } });
                        editor.root.innerHTML = $wire.{{ $property }} || '';
                        const reportValidity = () => window.dispatchEvent(new CustomEvent('tournament-editor-validity', { detail: { field: @js($property), valid: editor.getText().trim().length >= 10 } }));
                        editor.on('text-change', reportValidity);
                        reportValidity();
                    }; boot();
                ">
                    <label class="field-label">{{ $label }} *</label><div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900"><div x-ref="editor" class="ql-custom-dark h-56 overflow-y-auto text-slate-100"></div></div>
                </div>
                @error($property) <p class="-mt-4 field-error">{{ $message }}</p> @enderror
            @endforeach
        </section>

        <section data-step="3" x-show="step === 3" x-cloak class="p-5 md:p-8">
            <div class="mb-6"><h2 class="text-lg font-black text-white">Players & Match Settings</h2><p class="mt-1 text-sm text-slate-500">Configure team size, experience, match timing, and tournament streams.</p></div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
                <div><label class="field-label">Team Size *</label><input wire:model="team_size" required @disabled($isLocked) type="number" min="1" class="form-field"><p class="field-help">Use 1 for solo play.</p>@error('team_size')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label">Play XP *</label><input wire:model="play_xp" required type="number" min="0" max="1000000" class="form-field"><p class="field-help">For players who actually compete.</p>@error('play_xp')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label">Winner Bonus XP *</label><input wire:model="winner_bonus_xp" required type="number" min="0" max="1000000" class="form-field"><p class="field-help">Added to the champion's Play XP.</p>@error('winner_bonus_xp')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label">Result Submission Time *</label><input wire:model="waiting_result_time" required type="number" min="1" class="form-field"><p class="field-help">Minutes allowed to confirm a result.</p>@error('waiting_result_time')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="xl:col-span-2"><label class="field-label">Get Ready Time *</label><div class="grid grid-cols-[1fr_auto] gap-2"><input wire:model="match_ready_value" required type="number" min="1" max="365" class="form-field"><select wire:model="match_ready_unit" required class="form-field"><option value="minutes">Minutes</option><option value="hours">Hours</option><option value="days">Days</option></select></div><p class="field-help">Used before every match in every round.</p></div>
                <div class="xl:col-span-2"><label class="field-label">Extra Wait Time *</label><div class="grid grid-cols-[1fr_auto] gap-2"><input wire:model="match_extra_wait_value" required type="number" min="1" max="365" class="form-field"><select wire:model="match_extra_wait_unit" required class="form-field"><option value="minutes">Minutes</option><option value="hours">Hours</option><option value="days">Days</option></select></div><p class="field-help">Final allowance after an opponent is reported absent.</p></div>
                <div class="md:col-span-2 xl:col-span-4 flex items-start gap-3 rounded-xl border border-emerald-800/40 bg-emerald-950/15 p-4"><i data-lucide="shield-check" class="mt-0.5 h-4 w-4 text-emerald-400"></i><span><span class="block text-xs font-black uppercase text-emerald-200">Automatic Player Protection</span><span class="mt-1 block text-xs text-slate-500">The system first uses Extra Registration Time. If the minimum is still not reached, it cancels the tournament and refunds paid entries automatically.</span></span></div>
                <div class="md:col-span-2 xl:col-span-4 mt-2 border-t border-slate-800 pt-5"><h3 class="font-bold text-white">Live Stream Links</h3><p class="mt-1 text-xs text-slate-500">Optional tournament broadcasts shown inside PlayerSaloons.</p></div>
                <div><label class="field-label">YouTube</label><input wire:model="youtube_stream_url" type="url" placeholder="https://youtube.com/..." class="form-field">@error('youtube_stream_url')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label">Twitch</label><input wire:model="twitch_stream_url" type="url" placeholder="https://twitch.tv/..." class="form-field">@error('twitch_stream_url')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label">Facebook</label><input wire:model="facebook_stream_url" type="url" placeholder="https://facebook.com/..." class="form-field">@error('facebook_stream_url')<p class="field-error">{{ $message }}</p>@enderror</div>
            </div>
        </section>

        <section data-step="4" x-show="step === 4" x-cloak class="p-5 md:p-8">
            <div class="mb-6"><h2 class="text-lg font-black text-white">Dates & Timezone</h2><p class="mt-1 text-sm text-slate-500">Times below use the selected timezone. Player displays can convert them locally; storage remains UTC.</p></div>
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div class="lg:col-span-2"><label class="field-label">Tournament Timezone *</label><select wire:model.live="timezone" required class="form-field">@foreach($timezones as $zone)<option value="{{ $zone }}">{{ $zone }}</option>@endforeach</select>@error('timezone')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label">Registration Starts *</label><input wire:model="registration_open_at" required type="datetime-local" class="form-field">@error('registration_open_at')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label">Tournament Ends *</label><input wire:model="tournament_end_at" required type="datetime-local" class="form-field">@error('tournament_end_at')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label">Registration Duration *</label><div class="grid grid-cols-[1fr_auto] gap-2"><input wire:model="registration_duration_value" required type="number" min="1" max="365" class="form-field"><select wire:model="registration_duration_unit" required class="form-field"><option value="minutes">Minutes</option><option value="hours">Hours</option><option value="days">Days</option></select></div><p class="field-help">Entries lock when this duration ends.</p></div>
                <div><label class="field-label">Extra Registration Time *</label><div class="grid grid-cols-[1fr_auto] gap-2"><input wire:model="extra_registration_value" required type="number" min="0" max="365" class="form-field"><select wire:model="extra_registration_unit" required class="form-field"><option value="minutes">Minutes</option><option value="hours">Hours</option><option value="days">Days</option></select></div><p class="field-help">Used once only if the minimum is not reached.</p></div>
                <div class="lg:col-span-2 rounded-xl border border-indigo-500/20 bg-indigo-500/5 p-4 text-xs leading-relaxed text-slate-400">The estimated first match begins after Registration Duration plus Get Ready Time. If Extra Registration Time is needed, the first match and estimated end move by the same amount.</div>
            </div>
        </section>

        <section data-step="5" x-show="step === 5" x-cloak class="p-5 md:p-8">
            <div class="mb-6"><h2 class="text-lg font-black text-white">Fees, Capacity & Prizes</h2><p class="mt-1 text-sm text-slate-500">Place prizes share one prize pool and can never exceed it.</p></div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
                <div><label class="field-label">Entry Fee *</label><input wire:model="entry_fee" required @disabled($isLocked) type="number" min="0" step="0.01" class="form-field">@error('entry_fee')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label">Prize Pool *</label><input wire:model.live.debounce.250ms="prize_pool" required @disabled($isLocked) type="number" min="0" step="0.01" class="form-field">@error('prize_pool')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label">Minimum Players *</label><input wire:model="min_participants" required @disabled($isLocked || $competition_type === 'head_to_head') type="number" min="2" class="form-field">@error('min_participants')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label">Maximum Players *</label><input wire:model="max_participants" required @disabled($isLocked || $competition_type === 'head_to_head') type="number" min="2" class="form-field">@error('max_participants')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label text-amber-300">1st Place</label><input wire:model.live.debounce.250ms="prize_1st" @disabled($isLocked) type="number" min="0" step="0.01" class="form-field">@error('prize_1st')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label">2nd Place</label><input wire:model.live.debounce.250ms="prize_2nd" @disabled($isLocked) type="number" min="0" step="0.01" class="form-field">@error('prize_2nd')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label text-orange-300">3rd Place</label><input wire:model.live.debounce.250ms="prize_3rd" @disabled($isLocked) type="number" min="0" step="0.01" class="form-field">@error('prize_3rd')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4"><p class="text-xs font-bold uppercase text-slate-500">Prize Allocation</p><p class="mt-2 text-xl font-black" :class="((Number($wire.prize_1st)||0)+(Number($wire.prize_2nd)||0)+(Number($wire.prize_3rd)||0)) <= (Number($wire.prize_pool)||0) ? 'text-emerald-400' : 'text-red-400'" x-text="`${((Number($wire.prize_1st)||0)+(Number($wire.prize_2nd)||0)+(Number($wire.prize_3rd)||0)).toFixed(2)} / ${(Number($wire.prize_pool)||0).toFixed(2)}`"></p></div>
            </div>
        </section>

        <footer class="flex items-center justify-between border-t border-slate-800 bg-slate-950 px-5 py-4 md:px-8">
            <button x-show="step > 1" type="button" @click="previous" class="rounded-lg border border-slate-700 px-5 py-2.5 text-sm font-bold text-slate-300 hover:bg-slate-900">Back</button><span x-show="step === 1"></span>
            <button x-show="step < totalSteps" type="button" @click="next" :disabled="busy || !isStepComplete(step)" class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-black text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-40"><span x-show="!busy">Continue</span><span x-show="busy">Checking…</span></button>
            <button x-show="step === totalSteps" type="submit" :disabled="busy || !isStepComplete(step)" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-black text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-40"><span x-show="!busy">{{ $isEditMode ? 'Save Changes' : 'Create Tournament' }}</span><span x-show="busy">Saving…</span></button>
        </footer>
    </form>

    <style>
        [x-cloak] { display: none !important; }
        .field-label { margin-bottom: .5rem; display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .025em; color: rgb(148 163 184); }
        .field-help { margin-top: .25rem; font-size: .6875rem; color: rgb(100 116 139); }
        .field-error { margin-top: .25rem; font-size: .75rem; color: rgb(248 113 113); }
        .form-field { width: 100%; border-radius: .5rem; border: 1px solid rgb(30 41 59); background: rgb(15 23 42); padding: .625rem .75rem; font-size: .875rem; color: white; outline: none; }
        .form-field:focus { border-color: rgb(99 102 241); }
        .form-field:disabled { cursor: not-allowed; opacity: .5; }
        .ql-custom-dark .ql-editor { height: 14rem; overflow-y: auto; color: rgb(241 245 249); }
        .ql-toolbar.ql-snow, .ql-container.ql-snow { border-color: rgb(30 41 59) !important; }
        .ql-snow .ql-stroke { stroke: rgb(148 163 184) !important; }
        .ql-snow .ql-fill { fill: rgb(148 163 184) !important; }
    </style>
</div>
