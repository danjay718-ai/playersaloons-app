<div class="space-y-6">

    @if(session('success'))
        <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[420px_1fr]">
        <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-5">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-300">New campaign</p>
                    <h2 class="mt-1 text-lg font-bold text-white">Email subscribed players</h2>
                </div>
                <span class="rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3 py-1 text-xs font-bold text-indigo-200">{{ $subscriberCount }} recipients</span>
            </div>

            <form wire:submit="sendCampaign" class="space-y-4">
                <div>
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Subject</label>
                    <input wire:model="subject" type="text" maxlength="255" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                    @error('subject') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Message</label>
                    <textarea wire:model="content" rows="10" maxlength="10000" class="mt-2 w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm leading-6 text-white focus:border-indigo-500 focus:outline-none"></textarea>
                    @error('content') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <p class="text-xs leading-5 text-slate-500">Only active, verified users who currently opted in will receive this campaign. Every email includes a signed unsubscribe link.</p>
                <button type="submit" wire:loading.attr="disabled" wire:target="sendCampaign" @disabled($subscriberCount === 0)
                    class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-xs font-bold uppercase tracking-wider text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                    <span wire:loading.remove wire:target="sendCampaign">Send campaign now</span>
                    <span wire:loading wire:target="sendCampaign">Sending...</span>
                </button>
            </form>
        </section>

        <div class="space-y-6">
            <section class="rounded-xl border border-slate-800 bg-slate-950/60">
                <div class="flex flex-col gap-3 border-b border-slate-800 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-bold text-white">Subscriber audience</h2>
                        <p class="text-xs text-slate-500">Active and email-verified opt-ins</p>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search username or email"
                        class="rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none">
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-800 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="p-4">Player</th><th class="p-4">Email</th><th class="p-4">Subscribed</th></tr></thead>
                        <tbody class="divide-y divide-slate-800/70">
                            @forelse($subscribers as $subscriber)
                                <tr><td class="p-4 font-semibold text-slate-200">{{ $subscriber->username }}</td><td class="p-4 text-slate-400">{{ $subscriber->email }}</td><td class="p-4 text-slate-500">{{ $subscriber->newsletter_subscribed_at?->format('M d, Y') }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="p-8 text-center text-slate-500">No subscribers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-800 p-4">{{ $subscribers->links() }}</div>
            </section>

            <section class="rounded-xl border border-slate-800 bg-slate-950/60 p-5">
                <h2 class="mb-4 font-bold text-white">Recent campaigns</h2>
                <div class="space-y-3">
                    @forelse($campaigns as $campaign)
                        <div class="flex flex-col gap-2 rounded-lg border border-slate-800 bg-slate-900/50 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div><p class="text-sm font-semibold text-slate-200">{{ $campaign->subject }}</p><p class="mt-1 text-xs text-slate-500">{{ $campaign->creator->username }} · {{ $campaign->sent_at?->format('M d, Y h:i A') ?? 'Draft' }}</p></div>
                            <div class="text-xs text-slate-400"><span class="text-emerald-300">{{ $campaign->sent_count }} sent</span> · <span class="text-red-300">{{ $campaign->failed_count }} failed</span></div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-500">No campaigns sent yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
    <x-admin.deletion-actions resource="campaigns" />
</div>
