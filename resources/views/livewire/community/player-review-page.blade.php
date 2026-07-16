<div class="mx-auto w-full max-w-2xl space-y-6">
    @if(session('success'))<div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-200">{{ session('success') }}</div>@endif
    <section class="rounded-2xl border border-zinc-800 bg-zinc-950/70 p-6">
        <p class="font-orbitron text-[10px] font-black uppercase tracking-widest text-fuchsia-300">Player feedback</p><h1 class="mt-2 text-2xl font-black text-white">Rate your PlayerSaloons experience</h1>
        <p class="mt-2 text-sm text-zinc-500">Your review is moderated before it can appear publicly. Editing an approved review sends it back for approval.</p>
        @if($existing)<p class="mt-3 text-xs text-zinc-500">Current status: <span class="font-bold uppercase text-amber-300">{{ $existing->status }}</span></p>@endif
        <form wire:submit="submit" class="mt-6 space-y-5" x-data="{ rating: $wire.entangle('rating') }">
            <div>
                <label class="text-xs font-bold uppercase tracking-wider text-zinc-400">Star rating</label>
                <div class="mt-3 flex gap-2" role="radiogroup" aria-label="Star rating">
                    @for($star = 1; $star <= 5; $star++)
                        <button type="button" @click="rating = {{ $star }}"
                            role="radio" :aria-checked="rating === {{ $star }}" aria-label="{{ $star }} star{{ $star === 1 ? '' : 's' }}"
                            class="text-3xl transition-transform duration-100 hover:scale-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
                            :class="rating >= {{ $star }} ? 'text-amber-300' : 'text-zinc-700'">★</button>
                    @endfor
                </div>
                <p class="mt-2 text-xs text-zinc-500"><span x-text="rating"></span> out of 5 stars</p>
            </div>
            <div><label class="text-xs font-bold uppercase tracking-wider text-zinc-400">Review</label><textarea wire:model="review" rows="6" maxlength="1000" class="mt-2 w-full rounded-xl border border-zinc-800 bg-zinc-900 p-4 text-sm text-white" placeholder="Tell other players about your experience..."></textarea>@error('review')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror</div>
            <button class="rounded-xl bg-fuchsia-600 px-5 py-3 text-xs font-black uppercase tracking-wider text-white">Submit review</button>
        </form>
    </section>
</div>
