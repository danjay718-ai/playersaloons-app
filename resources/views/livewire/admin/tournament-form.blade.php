<div
    x-data="{
        step: 1,
        totalSteps: 5,
        busy: false,
        showOneTimeConfirmation: false,
        isEditMode: @js($isEditMode),
        validationTick: 0,
        editorValidity: { description: false, rules: false },
        isStepValid(stepNumber) {
            void this.validationTick; // tracked by Alpine for reactivity
            if (stepNumber === 2) {
                return true;
            }
            const section = this.$refs.form?.querySelector(`[data-step='${stepNumber}']`);
            if (!section) return false;
            return [...section.querySelectorAll('input, select, textarea')]
                .filter(f => !f.disabled)
                .every(f => {
                    if (f.dataset.invalidZero === 'true' && Number(f.value) <= 0) return false;
                    return (!f.required && f.value === '') || f.checkValidity();
                });
        },
        get canContinue() {
            return !this.busy && this.isStepValid(this.step);
        },
        recheckValidity() {
            // x-show toggles display but the DOM node persists — a small timeout
            // lets the browser finish any transition before we query fields.
            setTimeout(() => { this.validationTick++; }, 50);
        },
        async next() {
            if (!this.isStepValid(this.step)) return;
            window.dispatchEvent(new CustomEvent('sync-tournament-editors'));
            await new Promise(r => setTimeout(r, 75));
            this.busy = true;
            try {
                await $wire.validateStep(this.step);
                if (this.step < this.totalSteps) {
                    this.step++;
                    this.recheckValidity();
                }
            } catch(e) {
                // validation errors stay on the page; step does not advance
            } finally { this.busy = false; }
        },
        previous() {
            if (this.step > 1) {
                this.step--;
                this.recheckValidity();
            }
        },
        async save(publishOneTime = null) {
            if (!this.isEditMode && publishOneTime === null && this.$refs.frequency?.value === 'one-time') {
                this.showOneTimeConfirmation = true;
                return;
            }

            window.dispatchEvent(new CustomEvent('sync-tournament-editors'));
            await new Promise(r => setTimeout(r, 75));
            this.busy = true;
            try {
                if (publishOneTime === true) await $wire.chooseOneTimePublish();
                if (publishOneTime === false) await $wire.chooseOneTimeDraft();
                await $wire.saveTournament();
            } finally { this.busy = false; }
        }
    }"
    x-init="recheckValidity()"
    @input="validationTick++; recheckValidity()"
    @change="validationTick++; recheckValidity()"
    @image-crop-upload-finished.window="recheckValidity()"
    @tournament-editor-validity.window="editorValidity[$event.detail.field] = $event.detail.valid; recheckValidity()"
    class="w-full"
>
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-white">{{ $isEditMode ? 'Edit Legacy V1 Tournament' : 'Create Tournament (V1)' }}</h1>
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

    <div class="mb-6 rounded-xl border border-amber-800/50 bg-amber-950/20 p-4 text-xs text-amber-200">
        <strong class="font-black uppercase tracking-wider">Legacy V1 workflow.</strong>
        This editor is retained for existing V1 tournament records. New tournament schedules use the V2 schedule creator when Tournament V2 is enabled.
    </div>

    @if ($isLocked)
        <div class="mb-5 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200">
            Core tournament fields are locked because registration has already started. Schedule, content, timezone, and live stream links may still be updated.
        </div>
    @endif

    <div class="mb-6 grid grid-cols-5 gap-2">
        @foreach (['Details', 'Content', 'Match Setup', 'Schedule', 'Prizes'] as $number => $label)
            <button type="button" @click="if ({{ $number + 1 }} < step) { step = {{ $number + 1 }}; recheckValidity(); }" class="group text-left">
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
                <fieldset @disabled($isLocked) class="space-y-2">
                    <legend class="field-label">Game Type *</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="competition_type" value="tournament" class="peer sr-only">
                            <span class="flex min-h-12 items-center justify-center gap-2 rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400 transition peer-checked:border-indigo-400/60 peer-checked:bg-indigo-500/15 peer-checked:text-indigo-200 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C8.41 18.5 8 19.12 8 20h8c0-.88-.41-1.5-1.03-1.79-.5-.23-.97-.66-.97-1.21v-2.34"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>Tournament</span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="competition_type" value="head_to_head" class="peer sr-only">
                            <span class="flex min-h-12 items-center justify-center gap-2 rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-400 transition peer-checked:border-fuchsia-400/60 peer-checked:bg-fuchsia-500/15 peer-checked:text-fuchsia-200 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m14.5 17.5-11-11V3h3.5l11 11"/><path d="m13 19 6-6"/><path d="m16 16 3 3"/><path d="m19 21 2-2"/><path d="M14.5 6.5 21 13"/><path d="M18 3h3v3l-11 11"/><path d="m5 14-2 2"/><path d="m3 21 3-3"/></svg>Head-to-Head</span>
                        </label>
                    </div>
                    @error('competition_type') <p class="field-error">{{ $message }}</p> @enderror
                </fieldset>
                <div><label class="field-label">Game *</label><select wire:model.live="game_id" required data-invalid-zero="true" @disabled($isLocked) class="form-field"><option value="0">Select a game</option>@foreach($games as $game)<option value="{{ $game->id }}">{{ $game->translations->first()?->name ?? $game->slug }}</option>@endforeach</select>@error('game_id') <p class="field-error">{{ $message }}</p> @enderror</div>
                <fieldset><legend class="field-label">{{ __('Platforms') }} *</legend><div class="grid grid-cols-1 gap-2 sm:grid-cols-2">@foreach($platforms as $platform)<label class="flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200"><input type="checkbox" wire:model="platform_ids" value="{{ $platform->id }}" @disabled($isLocked) class="rounded border-slate-700 text-indigo-500">{{ $platform->name }}</label>@endforeach</div><p class="mt-2 text-xs text-slate-400">{{ __('Select all platforms players can use for this competition.') }}</p>@error('platform_ids')<p class="field-error">{{ $message }}</p>@enderror @error('platform_ids.*')<p class="field-error">{{ $message }}</p>@enderror</fieldset>
                <div><label class="field-label">Frequency *</label><select x-ref="frequency" wire:model.live="frequency" required @disabled($isLocked) class="form-field"><option value="" disabled>Select frequency</option><option value="one-time">One Time</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select>@error('frequency') <p class="field-error">{{ $message }}</p> @enderror</div>
                <div><label class="field-label">Entry Fee *</label><input wire:model="entry_fee" required @disabled($isLocked) type="number" min="0" step="0.01" class="form-field">@error('entry_fee')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="lg:col-span-2"><x-forms.image-crop-upload model="banner" label="Tournament Banner (Optional)" :width="960" :height="540" :max-mb="2" /></div>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 p-4"><input wire:model="is_featured" type="checkbox" class="mt-0.5 rounded border-slate-700 bg-slate-900 text-amber-500"><span><span class="block text-xs font-black uppercase text-amber-300">Featured Tournament</span><span class="mt-1 block text-xs leading-relaxed text-slate-500">Display this in the Featured section of the game page.</span></span></label>
            </div>
        </section>

        <section data-step="2" x-show="step === 2" x-cloak class="space-y-6 p-5 md:p-8">
            <div><h2 class="text-lg font-black text-white">Description & Rules</h2><p class="mt-1 text-sm text-slate-500">Both are optional. PlayerSaloons shows the standard fallback when either is left blank.</p></div>
            @foreach ([['description', 'Description (Optional)', 'Explain the tournament format and what players can expect.'], ['rules', 'Tournament Rules (Optional)', 'Add rules specific to this tournament.']] as [$property, $label, $placeholder])
                <div wire:ignore
                    x-data="{ editor: null, booted: false }"
                    @sync-tournament-editors.window="if (editor) $wire.{{ $property }} = editor.root.innerHTML"
                    x-effect="
                        if (step === 2 && !booted) {
                            booted = true;
                            const boot = () => {
                                if (typeof window.Quill === 'undefined') { setTimeout(boot, 75); return; }
                                editor = new Quill($refs.editor, {
                                    theme: 'snow',
                                    placeholder: @js($placeholder),
                                    modules: {
                                        toolbar: [
                                            [{ font: [] }, { size: ['small', false, 'large', 'huge'] }],
                                            [{ header: [1, 2, 3, 4, 5, 6, false] }],
                                            ['bold', 'italic', 'underline', 'strike'],
                                            [{ color: [] }, { background: [] }],
                                            [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
                                            [{ align: [] }],
                                            ['link', 'blockquote', 'code-block'],
                                            ['clean']
                                        ]
                                    }
                                });
                                editor.root.innerHTML = $wire.{{ $property }} || '';
                                const reportValidity = () => window.dispatchEvent(new CustomEvent('tournament-editor-validity', { detail: { field: @js($property), valid: true } }));
                                editor.on('text-change', reportValidity);
                                reportValidity();
                            };
                            boot();
                        }
                    "
                >
                    <label class="field-label">{{ $label }}</label>
                    <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
                        <div x-ref="editor" class="ql-custom-dark h-56 overflow-y-auto text-slate-100"></div>
                    </div>
                </div>
                @error($property) <p class="-mt-4 field-error">{{ $message }}</p> @enderror
            @endforeach
        </section>

        <section data-step="3" x-show="step === 3" x-cloak class="p-5 md:p-8">
            <div class="mb-6"><h2 class="text-lg font-black text-white">Players & Match Settings</h2><p class="mt-1 text-sm text-slate-500">Configure team size, experience, match timing, and tournament streams.</p></div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-12">
                <div class="xl:col-span-4"><label class="field-label">Team Size *</label><input wire:model="team_size" required @disabled($isLocked || $competition_type === 'head_to_head') type="number" min="0" class="form-field"><p class="field-help">Use 0 or 1 for solo play.</p>@error('team_size')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="xl:col-span-4"><label class="field-label">Minimum Players *</label><input wire:model="min_participants" required @disabled($isLocked || $competition_type === 'head_to_head') type="number" min="2" class="form-field">@error('min_participants')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="xl:col-span-4"><label class="field-label">Maximum Players *</label><input wire:model="max_participants" required @disabled($isLocked || $competition_type === 'head_to_head') type="number" min="2" class="form-field">@error('max_participants')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="xl:col-span-4"><label class="field-label">Get Ready Time *</label><div class="grid grid-cols-[minmax(0,1fr)_5.5rem] gap-2"><input wire:model="match_ready_value" required type="number" min="1" max="365" class="form-field"><select wire:model="match_ready_unit" required class="form-field"><option value="minutes">Minutes</option><option value="hours">Hours</option><option value="days">Days</option></select></div><p class="field-help">Before every match.</p></div>
                <div class="xl:col-span-4"><label class="field-label">Extra Wait Time *</label><div class="grid grid-cols-[minmax(0,1fr)_5.5rem] gap-2"><input wire:model="match_extra_wait_value" required type="number" min="1" max="365" class="form-field"><select wire:model="match_extra_wait_unit" required class="form-field"><option value="minutes">Minutes</option><option value="hours">Hours</option><option value="days">Days</option></select></div><p class="field-help">Final absent-opponent allowance.</p></div>
                <div class="xl:col-span-4"><label class="field-label">Result Submission Time *</label><input wire:model="waiting_result_time" required type="number" min="1" class="form-field"><p class="field-help">Minutes to confirm.</p>@error('waiting_result_time')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="xl:col-span-4"><label class="field-label">Play XP *</label><input wire:model="play_xp" required type="number" min="0" max="1000000" class="form-field"><p class="field-help">For players who actually compete.</p>@error('play_xp')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="xl:col-span-4"><label class="field-label">Winner Bonus XP *</label><input wire:model="winner_bonus_xp" required type="number" min="0" max="1000000" class="form-field"><p class="field-help">Added to the champion's Play XP.</p>@error('winner_bonus_xp')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="md:col-span-2 xl:col-span-12 flex items-start gap-3 rounded-xl border border-emerald-800/40 bg-emerald-950/15 p-4"><i data-lucide="shield-check" class="mt-0.5 h-4 w-4 text-emerald-400"></i><span><span class="block text-xs font-black uppercase text-emerald-200">Automatic Player Protection</span><span class="mt-1 block text-xs text-slate-500">The system first uses Extra Registration Time. If the minimum is still not reached, it cancels the tournament and refunds paid entries automatically.</span></span></div>
                <div class="md:col-span-2 xl:col-span-12 mt-2 border-t border-slate-800 pt-5"><h3 class="font-bold text-white">Live Stream Links</h3><p class="mt-1 text-xs text-slate-500">Optional tournament broadcasts shown inside PlayerSaloons.</p></div>
                <div class="xl:col-span-4"><label class="field-label">YouTube</label><input wire:model="youtube_stream_url" type="url" placeholder="https://youtube.com/..." class="form-field">@error('youtube_stream_url')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="xl:col-span-4"><label class="field-label">Twitch</label><input wire:model="twitch_stream_url" type="url" placeholder="https://twitch.tv/..." class="form-field">@error('twitch_stream_url')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="xl:col-span-4"><label class="field-label">Facebook</label><input wire:model="facebook_stream_url" type="url" placeholder="https://facebook.com/..." class="form-field">@error('facebook_stream_url')<p class="field-error">{{ $message }}</p>@enderror</div>
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
            <div class="mb-6"><h2 class="text-lg font-black text-white">Prizes</h2><p class="mt-1 text-sm text-slate-500">Place prizes share one prize pool and can never exceed it.</p></div>
            <div class="mb-5 flex gap-3 rounded-xl border border-amber-500/25 bg-amber-500/10 p-4 text-xs leading-relaxed text-amber-100/80"><i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0 text-amber-300"></i><p><strong class="text-amber-200">Prize payout notice:</strong> at the minimum player count, prizes pay at 50% of the advertised amount and scale up to 100% when the tournament is full. The current platform commission is also deducted from every winner payout. Change the commission in <strong class="text-amber-200">System Settings</strong>, or ask a Super Admin.</p></div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
                <div><label class="field-label">Prize Pool *</label><input wire:model.live.debounce.250ms="prize_pool" required @disabled($isLocked) type="number" min="0" step="0.01" class="form-field">@error('prize_pool')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label text-amber-300">1st Place</label><input wire:model.live.debounce.250ms="prize_1st" @disabled($isLocked) type="number" min="0" step="0.01" class="form-field">@error('prize_1st')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label">2nd Place (Optional)</label><input wire:model.live.debounce.250ms="prize_2nd" @disabled($isLocked) type="number" min="0" step="0.01" class="form-field">@error('prize_2nd')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="field-label text-orange-300">3rd Place (Optional)</label><input wire:model.live.debounce.250ms="prize_3rd" @disabled($isLocked) type="number" min="0" step="0.01" class="form-field">@error('prize_3rd')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4"><p class="text-xs font-bold uppercase text-slate-500">Prize Allocation</p><p class="mt-2 text-xl font-black" :class="((Number($wire.prize_1st)||0)+(Number($wire.prize_2nd)||0)+(Number($wire.prize_3rd)||0)) <= (Number($wire.prize_pool)||0) ? 'text-emerald-400' : 'text-red-400'" x-text="`${((Number($wire.prize_1st)||0)+(Number($wire.prize_2nd)||0)+(Number($wire.prize_3rd)||0)).toFixed(2)} / ${(Number($wire.prize_pool)||0).toFixed(2)}`"></p></div>
            </div>
        </section>

        <footer class="flex items-center justify-between border-t border-slate-800 bg-slate-950 px-5 py-4 md:px-8">
            <button x-show="step > 1" type="button" @click="previous" class="rounded-lg border border-slate-700 px-5 py-2.5 text-sm font-bold text-slate-300 hover:bg-slate-900">Back</button><span x-show="step === 1"></span>
            <button x-show="step < totalSteps" type="button" @click="next" :disabled="!canContinue" class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-black text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-40"><span x-show="!busy">Continue</span><span x-show="busy">Checking…</span></button>
            <button x-show="step === totalSteps" type="submit" :disabled="!canContinue" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-black text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-40"><span x-show="!busy">{{ $isEditMode ? 'Save Changes' : 'Create Tournament' }}</span><span x-show="busy">Saving…</span></button>
        </footer>
    </form>

    <div x-cloak x-show="showOneTimeConfirmation" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" @keydown.escape.window="showOneTimeConfirmation = false">
        <div @click.outside="showOneTimeConfirmation = false" role="dialog" aria-modal="true" aria-labelledby="one-time-confirmation-title" class="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-500/15 text-amber-300"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><circle cx="12" cy="15" r="3"/><path d="M12 13.5V15l1 1"/></svg></div>
            <h2 id="one-time-confirmation-title" class="mt-4 text-lg font-black text-white">One-time tournament</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-400">This option applies only to one-time tournaments. Choose whether to keep it as a draft for review or publish it now so players can see and join it.</p>
            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                <button type="button" @click="showOneTimeConfirmation = false; save(false)" class="rounded-lg border border-slate-600 bg-slate-800 px-4 py-3 text-sm font-black text-slate-100 hover:bg-slate-700">Save as Draft</button>
                <button type="button" @click="showOneTimeConfirmation = false; save(true)" class="rounded-lg bg-emerald-600 px-4 py-3 text-sm font-black text-white hover:bg-emerald-500">Publish Now</button>
            </div>
            <button type="button" @click="showOneTimeConfirmation = false" class="mt-4 w-full text-sm font-bold text-slate-400 hover:text-white">Cancel</button>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
        .field-label { margin-bottom: .5rem; display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .025em; color: rgb(148 163 184); }
        .field-help { margin-top: .25rem; font-size: .6875rem; color: rgb(100 116 139); }
        .field-error { margin-top: .25rem; font-size: .75rem; color: rgb(248 113 113); }
        .form-field { width: 100%; border-radius: .5rem; border: 1px solid rgb(30 41 59); background: rgb(15 23 42); padding: .625rem .75rem; font-size: .875rem; color: white; outline: none; }
        .form-field:focus { border-color: rgb(99 102 241); }
        .form-field:disabled { cursor: not-allowed; opacity: .5; }

        /* Quill dark theme */
        .ql-toolbar.ql-snow, .ql-container.ql-snow { border-color: rgb(30 41 59) !important; }
        .ql-toolbar.ql-snow { background: rgb(2 6 23); border-radius: .75rem .75rem 0 0; flex-wrap: wrap; }
        .ql-container.ql-snow { border-radius: 0 0 .75rem .75rem; }
        .ql-snow .ql-stroke { stroke: rgb(148 163 184) !important; }
        .ql-snow .ql-fill { fill: rgb(148 163 184) !important; }
        .ql-snow .ql-picker { color: rgb(148 163 184) !important; }
        .ql-snow .ql-picker-label { color: rgb(148 163 184) !important; border-color: rgb(30 41 59) !important; }
        .ql-snow .ql-picker-label:hover, .ql-snow .ql-picker-label.ql-active { color: rgb(255 255 255) !important; }
        .ql-snow .ql-picker-options { background: rgb(15 23 42) !important; border-color: rgb(30 41 59) !important; border-radius: .5rem; box-shadow: 0 4px 20px rgba(0,0,0,.6); }
        .ql-snow .ql-picker-item { color: rgb(148 163 184) !important; }
        .ql-snow .ql-picker-item:hover, .ql-snow .ql-picker-item.ql-selected { color: rgb(255 255 255) !important; background: rgb(30 41 59) !important; }
        .ql-snow .ql-tooltip { background: rgb(15 23 42) !important; border-color: rgb(30 41 59) !important; color: rgb(226 232 240) !important; box-shadow: 0 4px 16px rgba(0,0,0,.5); border-radius: .5rem; }
        .ql-snow .ql-tooltip input[type=text] { background: rgb(2 6 23); border-color: rgb(30 41 59); color: white; border-radius: .25rem; }
        .ql-snow .ql-tooltip a { color: rgb(99 102 241) !important; }
        .ql-toolbar.ql-snow .ql-formats { margin-right: .5rem; }
        .ql-snow button:hover .ql-stroke, .ql-snow button.ql-active .ql-stroke { stroke: rgb(255 255 255) !important; }
        .ql-snow button:hover .ql-fill, .ql-snow button.ql-active .ql-fill { fill: rgb(255 255 255) !important; }
        .ql-custom-dark .ql-editor { min-height: 14rem; overflow-y: auto; color: rgb(241 245 249); font-size: .875rem; line-height: 1.6; }
        .ql-editor.ql-blank::before { color: rgb(100 116 139) !important; font-style: italic; }

        /* Color / background picker swatches */
        .ql-snow .ql-color-picker .ql-picker-label svg,
        .ql-snow .ql-background .ql-picker-label svg { width: 16px; height: 16px; }
    </style>
</div>
