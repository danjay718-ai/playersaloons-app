<div>
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <!-- Search and Filters -->
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <input type="text" wire:model.live="search" placeholder="Search by username or email..." 
                   class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 w-full sm:w-64">
            
            <select wire:model.live="statusFilter" 
                    class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
                <option value="">All Statuses</option>
                @foreach(\App\Shared\Enums\WithdrawalStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ strtoupper($status->name) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if(session()->has('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session()->has('info'))
        <div class="bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <i data-lucide="info" class="w-4 h-4 mr-2"></i>
            <span>{{ session('info') }}</span>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
            <i data-lucide="alert-circle" class="w-4 h-4 mr-2"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Withdrawals Table -->
    <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                        <th class="p-4">User</th>
                        <th class="p-4">Amount</th>
                        <th class="p-4">Requested At</th>
                        <th class="p-4">Reviewer</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @forelse($withdrawals as $withdrawal)
                        <tr class="hover:bg-slate-900/40">
                            <td class="p-4 font-semibold text-slate-200">
                                <span>{{ $withdrawal->user->username }}</span>
                                <span class="block text-[10px] text-slate-500 font-normal mt-0.5">{{ $withdrawal->user->email }}</span>
                            </td>
                            <td class="p-4 text-slate-200 font-bold">
                                ${{ number_format((float)$withdrawal->amount, 2) }}
                            </td>
                            <td class="p-4 text-slate-450">
                                {{ $withdrawal->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="p-4 text-slate-400">
                                @if($withdrawal->reviewer)
                                    <span>{{ $withdrawal->reviewer->username }}</span>
                                @else
                                    <span class="text-slate-550 italic">—</span>
                                @endif
                            </td>
                            <td class="p-4">
                                @php
                                    $withColors = [
                                        'pending' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20 animate-pulse',
                                        'under_review' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                        'approved' => 'bg-emerald-500/10 text-emerald-450 border-emerald-500/20',
                                        'rejected' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                        'processed' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                                    ];
                                    $col = $withColors[$withdrawal->status->value] ?? 'bg-slate-800 text-slate-400 border-slate-750';
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold uppercase {{ $col }}">
                                    {{ str_replace('_', ' ', $withdrawal->status->value) }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <button wire:click="selectWithdrawal({{ $withdrawal->id }})" class="px-3 py-1 bg-slate-800 hover:bg-slate-750 border border-slate-700 text-slate-200 font-bold rounded-lg text-[10px] uppercase tracking-wider">
                                    Review
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500 italic">No withdrawal requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div>
        {{ $withdrawals->links() }}
    </div>

    <!-- Detail Modal -->
    @if($showDetailModal && $selectedWithdrawal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showDetailModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-3xl w-full overflow-hidden shadow-2xl relative z-10 max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Review Withdrawal Request</h3>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5">{{ $selectedWithdrawal->uuid }}</p>
                    </div>
                    <button wire:click="$set('showDetailModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-6 flex-grow text-xs">
                    <!-- Warnings / Four-eyes Checks -->
                    @if(auth()->id() === $selectedWithdrawal->user_id)
                        <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-3.5 rounded-lg flex items-start">
                            <i data-lucide="alert-triangle" class="w-4 h-4 mr-2 mt-0.5"></i>
                            <div>
                                <p class="font-bold">Four-Eyes Violation Warning</p>
                                <p class="mt-0.5 text-[11px]">You are the requester of this withdrawal. Under platform security policies, you cannot self-approve or self-review/reject your own request.</p>
                            </div>
                        </div>
                    @endif

                    @if(!$userKyc || $userKyc->status->value !== 'approved')
                        <div class="bg-amber-500/10 border border-amber-500/20 text-amber-400 p-3.5 rounded-lg flex items-start">
                            <i data-lucide="shield-alert" class="w-4 h-4 mr-2 mt-0.5"></i>
                            <div>
                                <p class="font-bold">KYC Validation Pending</p>
                                <p class="mt-0.5 text-[11px]">This user does not have an approved KYC document. KYC approval is required prior to processing payouts.</p>
                            </div>
                        </div>
                    @endif

                    <!-- User and Amount Summary -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-[#0b0f19] border border-slate-800 rounded-xl p-4">
                        <div>
                            <span class="text-slate-550 block">Requester</span>
                            <span class="text-slate-200 font-semibold mt-1 block">{{ $selectedWithdrawal->user->username }}</span>
                        </div>
                        <div>
                            <span class="text-slate-550 block">Amount Requested</span>
                            <span class="text-2xl font-black text-slate-100 mt-1 block">${{ number_format((float)$selectedWithdrawal->amount, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-slate-550 block">KYC Status</span>
                            <span class="inline-block mt-1 px-2 py-0.5 rounded border font-bold uppercase text-[9px]
                                  {{ $userKyc && $userKyc->status->value === 'approved' ? 'bg-emerald-500/10 text-emerald-450 border-emerald-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20' }}">
                                {{ $userKyc ? str_replace('_', ' ', $userKyc->status->value) : 'NONE' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-550 block">Request Status</span>
                            <span class="inline-block mt-1 px-2 py-0.5 rounded border font-bold uppercase text-[9px]
                                  {{ $selectedWithdrawal->status->value === 'processed' ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' : ($selectedWithdrawal->status->value === 'approved' ? 'bg-emerald-500/10 text-emerald-450 border-emerald-500/20' : 'bg-blue-500/10 text-blue-400 border-blue-500/20') }}">
                                {{ str_replace('_', ' ', $selectedWithdrawal->status->value) }}
                            </span>
                        </div>
                    </div>

                    <!-- Notes / Review Audit -->
                    @if($selectedWithdrawal->reviewer)
                        <div class="bg-slate-900 border border-slate-850 p-4 rounded-xl">
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Review details</span>
                            <div class="grid grid-cols-2 gap-4 mt-2">
                                <div>
                                    <span class="text-slate-500 block">Reviewed By</span>
                                    <span class="text-slate-350 font-semibold">{{ $selectedWithdrawal->reviewer->username }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-550 block">Reviewed At</span>
                                    <span class="text-slate-350">{{ $selectedWithdrawal->reviewed_at ? $selectedWithdrawal->reviewed_at->format('Y-m-d H:i') : 'N/A' }}</span>
                                </div>
                            </div>
                            @if($selectedWithdrawal->review_notes)
                                <div class="mt-3">
                                    <span class="text-slate-550 block">Review Notes</span>
                                    <p class="text-slate-400 mt-1 bg-slate-950 p-2.5 rounded border border-slate-800/40 leading-relaxed">{{ $selectedWithdrawal->review_notes }}</p>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- User Wallet history -->
                    <div>
                        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block mb-2">User Wallet Transaction History (Last 10)</span>
                        <div class="bg-slate-900/40 border border-slate-800/60 rounded-lg overflow-hidden">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-900 border-b border-slate-800 text-[10px] text-slate-500 font-bold uppercase">
                                        <th class="p-3">Reference</th>
                                        <th class="p-3">Type</th>
                                        <th class="p-3">Amount</th>
                                        <th class="p-3">Balance After</th>
                                        <th class="p-3">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/40">
                                    @forelse($walletHistory as $entry)
                                        <tr>
                                            <td class="p-3 font-semibold text-slate-300">
                                                <span class="block">{{ str_replace('_', ' ', $entry->reference_type) }}</span>
                                                <span class="block text-[9px] text-slate-500 font-normal mt-0.5">{{ $entry->description }}</span>
                                            </td>
                                            <td class="p-3 capitalize">
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase {{ $entry->type === 'credit' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                                                    {{ $entry->type }}
                                                </span>
                                            </td>
                                            <td class="p-3 text-slate-300">
                                                ${{ number_format($entry->amount, 2) }}
                                            </td>
                                            <td class="p-3 text-slate-400 font-mono">
                                                ${{ number_format($entry->running_balance, 2) }}
                                            </td>
                                            <td class="p-3 text-slate-500">
                                                {{ $entry->created_at->format('M d, H:i') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="p-4 text-center text-slate-500 italic">No wallet ledger transactions found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-800 bg-[#0b0f19] flex justify-end space-x-3">
                    @if(auth()->id() !== $selectedWithdrawal->user_id)
                        @if($selectedWithdrawal->status->value === 'under_review' && $userKyc && $userKyc->status->value === 'approved')
                            <button wire:click="openRejectModal" class="bg-red-950 hover:bg-red-900 border border-red-900/50 text-red-400 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                                Reject Request
                            </button>
                            <button wire:click="openApproveModal" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                                Approve Request
                            </button>
                        @endif
                    @endif

                    @if($selectedWithdrawal->status->value === 'approved')
                        <button wire:click="processPayout" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Mark as Paid / Processed
                        </button>
                    @endif

                    <button wire:click="$set('showDetailModal', false)" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                        Close Detail
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Approve Notes Modal -->
    @if($showApproveModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" wire:click="$set('showApproveModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Approve Withdrawal Request</h3>
                    <button wire:click="$set('showApproveModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="approve" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Approval Notes (Optional)</label>
                        <textarea wire:model="approveNotes" placeholder="e.g. Account and details reviewed, looks correct." rows="3"
                                  class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500"></textarea>
                        @error('approveNotes') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showApproveModal', false)" 
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Confirm Approval
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Reject Request Reason Modal -->
    @if($showRejectModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" wire:click="$set('showRejectModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-red-400 uppercase tracking-wider">Reject Withdrawal Request</h3>
                    <button wire:click="$set('showRejectModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="reject" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Reason for Rejection</label>
                        <input type="text" wire:model="rejectReason" placeholder="e.g. Suspected duplicate accounts"
                               class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-red-500">
                        @error('rejectReason') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showRejectModal', false)" 
                                class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-red-600 hover:bg-red-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Reject Payout
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
