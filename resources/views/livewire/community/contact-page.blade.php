<div class="mx-auto grid max-w-6xl gap-6 py-6 lg:grid-cols-[0.9fr_1.1fr]">
    <section class="landing-card gr-surface-card rounded-2xl border border-white/10 bg-[#0e1726] p-6 sm:p-8">
        <div class="flex items-center gap-3.5">
            <div class="grid h-12 w-12 place-items-center rounded-xl border border-[#22e6ff]/35 bg-[#22e6ff]/10 text-[#22e6ff]">
                <i data-lucide="headphones" class="h-5 w-5"></i>
            </div>
            <div>
                <p class="gr-eyebrow !mb-1"><span class="gr-slash" aria-hidden="true">//</span> Support</p>
                <h1 class="font-orbitron text-2xl font-black uppercase tracking-wide text-white">Contact GamersRival</h1>
            </div>
        </div>

        <p class="mt-5 text-sm leading-6 text-[#a8b6c9]">
            Send questions about accounts, tournaments, wallet activity, KYC, or platform issues. If you are signed in, your account is linked automatically.
        </p>

        <div class="mt-8 grid gap-3 text-sm text-zinc-200">
            <div class="flex items-start gap-3 rounded-xl border border-white/10 bg-[#070d17] px-4 py-3.5">
                <i data-lucide="shield-question" class="mt-0.5 h-4 w-4 shrink-0 text-[#22e6ff]"></i>
                <span>Use the right category so staff can route the request faster.</span>
            </div>
            <div class="flex items-start gap-3 rounded-xl border border-white/10 bg-[#070d17] px-4 py-3.5">
                <i data-lucide="receipt-text" class="mt-0.5 h-4 w-4 shrink-0 text-[#c6ff3d]"></i>
                <span>For wallet, payout, or tournament questions, include relevant tournament names or match details.</span>
            </div>
            <div class="flex items-start gap-3 rounded-xl border border-white/10 bg-[#070d17] px-4 py-3.5">
                <i data-lucide="mail-check" class="mt-0.5 h-4 w-4 shrink-0 text-[#ffd23f]"></i>
                <span>Staff replies will use the email address submitted with the inquiry.</span>
            </div>
        </div>
    </section>

    <section class="landing-card gr-surface-card rounded-2xl border border-white/10 bg-[#0e1726] p-6 sm:p-8">
        @if (session('success'))
            <div class="mb-5 rounded-xl border border-[#c6ff3d]/40 bg-[#c6ff3d]/10 px-4 py-3 text-sm font-semibold text-[#c6ff3d]">
                {{ session('success') }}
            </div>
        @endif

        <form wire:submit.prevent="submit" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="contact-name" class="block text-xs font-bold uppercase tracking-wider text-[#a8b6c9]">Name</label>
                    <input wire:model="name" id="contact-name" type="text" required
                        class="mt-1.5 block w-full rounded-xl border border-white/15 bg-[#070d17] px-3.5 py-2.5 text-sm text-white placeholder-zinc-500 focus:border-[#c6ff3d] focus:outline-none focus:ring-1 focus:ring-[#c6ff3d]">
                    @error('name') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="contact-email" class="block text-xs font-bold uppercase tracking-wider text-[#a8b6c9]">Email</label>
                    <input wire:model="email" id="contact-email" type="email" required
                        class="mt-1.5 block w-full rounded-xl border border-white/15 bg-[#070d17] px-3.5 py-2.5 text-sm text-white placeholder-zinc-500 focus:border-[#c6ff3d] focus:outline-none focus:ring-1 focus:ring-[#c6ff3d]">
                    @error('email') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-[0.8fr_1.2fr]">
                <div>
                    <label for="contact-category" class="block text-xs font-bold uppercase tracking-wider text-[#a8b6c9]">Category</label>
                    <select wire:model="category" id="contact-category"
                        class="mt-1.5 block w-full rounded-xl border border-white/15 bg-[#070d17] px-3.5 py-2.5 text-sm text-white focus:border-[#c6ff3d] focus:outline-none focus:ring-1 focus:ring-[#c6ff3d]">
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('category') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="contact-subject" class="block text-xs font-bold uppercase tracking-wider text-[#a8b6c9]">Subject</label>
                    <input wire:model="subject" id="contact-subject" type="text" required
                        class="mt-1.5 block w-full rounded-xl border border-white/15 bg-[#070d17] px-3.5 py-2.5 text-sm text-white placeholder-zinc-500 focus:border-[#c6ff3d] focus:outline-none focus:ring-1 focus:ring-[#c6ff3d]"
                        placeholder="Short summary">
                    @error('subject') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label for="contact-message" class="block text-xs font-bold uppercase tracking-wider text-[#a8b6c9]">Message</label>
                <textarea wire:model="message" id="contact-message" rows="7" required
                    class="mt-1.5 block w-full resize-y rounded-xl border border-white/15 bg-[#070d17] px-3.5 py-2.5 text-sm text-white placeholder-zinc-500 focus:border-[#c6ff3d] focus:outline-none focus:ring-1 focus:ring-[#c6ff3d]"
                    placeholder="Tell us what happened or what you need help with."></textarea>
                @error('message') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
            </div>

            <button type="submit"
                wire:loading.attr="disabled"
                wire:target="submit"
                class="gr-btn gr-btn-primary w-full disabled:cursor-not-allowed disabled:opacity-70">
                <i data-lucide="send" class="h-4 w-4"></i>
                <span wire:loading.remove wire:target="submit">Send Inquiry</span>
                <span wire:loading wire:target="submit">Sending Inquiry...</span>
            </button>
        </form>
    </section>
</div>
