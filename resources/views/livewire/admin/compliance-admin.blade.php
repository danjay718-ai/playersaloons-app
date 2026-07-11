<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-100">Compliance & Blacklisting</h1>
            <p class="mt-1 text-sm text-slate-500">Review access restrictions and their audit history.</p>
        </div>
        <select wire:change="openApply($event.target.value)" class="rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-300">
            <option value="">Apply block to player...</option>
            @foreach($eligibleUsers as $user)
                <option value="{{ $user->id }}">{{ $user->username }} ({{ $user->email }})</option>
            @endforeach
        </select>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">{{ session('success') }}</div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search username or email" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-200 sm:max-w-sm">
        <select wire:model.live="statusFilter" class="rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-300">
            <option value="active">Active</option>
            <option value="expired">Expired</option>
            <option value="revoked">Revoked</option>
            <option value="all">All records</option>
        </select>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-800 bg-slate-950">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-800 bg-slate-900 text-xs uppercase text-slate-500">
                    <tr><th class="p-4">Player</th><th class="p-4">Category</th><th class="p-4">Reason</th><th class="p-4">Expires</th><th class="p-4">State</th><th class="p-4"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($blocks as $block)
                        @php($active = !$block->revoked_at && (!$block->expires_at || $block->expires_at->isFuture()))
                        <tr class="text-slate-300">
                            <td class="p-4"><span class="block font-semibold">{{ $block->user->username }}</span><span class="text-xs text-slate-500">{{ $block->user->email }}</span></td>
                            <td class="p-4 uppercase text-xs">{{ str_replace('_', ' ', $block->category) }}</td>
                            <td class="max-w-md p-4 text-slate-400">{{ $block->reason }}</td>
                            <td class="p-4 text-xs">{{ $block->expires_at?->format('Y-m-d H:i') ?? 'Permanent' }}</td>
                            <td class="p-4"><span class="text-xs font-bold {{ $active ? 'text-red-400' : 'text-slate-500' }}">{{ $active ? 'ACTIVE' : ($block->revoked_at ? 'REVOKED' : 'EXPIRED') }}</span></td>
                            <td class="p-4 text-right">@if($active)<button wire:click="openRevoke({{ $block->id }})" class="rounded-md border border-slate-700 p-2 text-slate-400 hover:text-white" title="Revoke block"><i data-lucide="shield-check" class="h-4 w-4"></i></button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-10 text-center text-slate-500">No compliance records match this view.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $blocks->links() }}

    @if($showApplyModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">
            <form wire:submit="applyBlock" class="w-full max-w-lg space-y-4 rounded-lg border border-slate-800 bg-slate-950 p-6">
                <h2 class="font-bold text-white">Apply compliance block</h2>
                <select wire:model="category" class="w-full rounded-lg border border-slate-800 bg-slate-900 p-3 text-sm text-slate-200"><option value="platform_abuse">Platform abuse</option><option value="fraud">Fraud</option><option value="chargeback">Chargeback</option><option value="identity_risk">Identity risk</option><option value="legal_restriction">Legal restriction</option></select>
                <textarea wire:model="reason" rows="4" placeholder="Document the evidence and reason" class="w-full rounded-lg border border-slate-800 bg-slate-900 p-3 text-sm text-slate-200"></textarea>
                @error('reason')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                <input wire:model="expiresAt" type="datetime-local" class="w-full rounded-lg border border-slate-800 bg-slate-900 p-3 text-sm text-slate-200">
                <div class="flex justify-end gap-3"><button type="button" wire:click="$set('showApplyModal', false)" class="px-4 py-2 text-sm text-slate-400">Cancel</button><button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white">Apply block</button></div>
            </form>
        </div>
    @endif

    @if($showRevokeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">
            <form wire:submit="revokeBlock" class="w-full max-w-lg space-y-4 rounded-lg border border-slate-800 bg-slate-950 p-6">
                <h2 class="font-bold text-white">Revoke compliance block</h2>
                <textarea wire:model="revocationReason" rows="3" placeholder="Reason for restoring access" class="w-full rounded-lg border border-slate-800 bg-slate-900 p-3 text-sm text-slate-200"></textarea>
                @error('revocationReason')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                <div class="flex justify-end gap-3"><button type="button" wire:click="$set('showRevokeModal', false)" class="px-4 py-2 text-sm text-slate-400">Cancel</button><button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white">Restore access</button></div>
            </form>
        </div>
    @endif
</div>
