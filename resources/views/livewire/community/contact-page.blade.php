<div class="mx-auto grid max-w-6xl gap-6 py-6 lg:grid-cols-[0.9fr_1.1fr]">
    <section class="rounded-lg border border-zinc-800 bg-zinc-900 p-6 shadow-xl shadow-black/20">
        <div class="flex items-center gap-3">
            <div class="grid h-11 w-11 place-items-center rounded-lg border border-cyan-400/30 bg-cyan-500/10 text-cyan-200">
                <i data-lucide="headphones" class="h-5 w-5"></i>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.24em] text-cyan-300">Support</p>
                <h1 class="font-orbitron text-2xl font-black uppercase tracking-wide text-white">Contact PlayerSaloons</h1>
            </div>
        </div>

        <p class="mt-5 text-sm leading-6 text-zinc-400">
            Send questions about accounts, tournaments, wallet activity, KYC, or platform issues. If you are signed in, your account is linked automatically.
        </p>

        <div class="mt-8 grid gap-3 text-sm text-zinc-300">
            <div class="flex items-start gap-3 rounded-lg border border-zinc-800 bg-zinc-950 px-4 py-3">
                <i data-lucide="shield-question" class="mt-0.5 h-4 w-4 shrink-0 text-violet-300"></i>
                <span>Use the right category so staff can route the request faster.</span>
            </div>
            <div class="flex items-start gap-3 rounded-lg border border-zinc-800 bg-zinc-950 px-4 py-3">
                <i data-lucide="receipt-text" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-300"></i>
                <span>For wallet, payout, or tournament questions, include relevant tournament names or match details.</span>
            </div>
            <div class="flex items-start gap-3 rounded-lg border border-zinc-800 bg-zinc-950 px-4 py-3">
                <i data-lucide="mail-check" class="mt-0.5 h-4 w-4 shrink-0 text-amber-300"></i>
                <span>Staff replies will use the email address submitted with the inquiry.</span>
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-zinc-800 bg-zinc-900 p-6 shadow-xl shadow-black/20">
        @if (session('success'))
            <div class="mb-5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        <form wire:submit.prevent="submit" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="contact-name" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Name</label>
                    <input wire:model="name" id="contact-name" type="text" required
                        class="mt-1.5 block w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm text-zinc-200 placeholder-zinc-600 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                    @error('name') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="contact-email" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Email</label>
                    <input wire:model="email" id="contact-email" type="email" required
                        class="mt-1.5 block w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm text-zinc-200 placeholder-zinc-600 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                    @error('email') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-[0.8fr_1.2fr]">
                <div>
                    <label for="contact-category" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Category</label>
                    <select wire:model="category" id="contact-category"
                        class="mt-1.5 block w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm text-zinc-200 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('category') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="contact-subject" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Subject</label>
                    <input wire:model="subject" id="contact-subject" type="text" required
                        class="mt-1.5 block w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm text-zinc-200 placeholder-zinc-600 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                        placeholder="Short summary">
                    @error('subject') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label for="contact-message" class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">Message</label>
                <textarea wire:model="message" id="contact-message" rows="7" required
                    class="mt-1.5 block w-full resize-y rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2.5 text-sm text-zinc-200 placeholder-zinc-600 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    placeholder="Tell us what happened or what you need help with."></textarea>
                @error('message') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
            </div>

            <button type="submit"
                wire:loading.attr="disabled"
                wire:target="submit"
                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-cyan-600 to-violet-600 px-4 py-3 text-sm font-black text-white shadow-lg shadow-cyan-950/30 transition hover:from-cyan-500 hover:to-violet-500 disabled:cursor-not-allowed disabled:opacity-70">
                <i data-lucide="send" class="h-4 w-4"></i>
                <span wire:loading.remove wire:target="submit">Send Inquiry</span>
                <span wire:loading wire:target="submit">Sending Inquiry...</span>
            </button>
        </form>
    </section>
</div>
