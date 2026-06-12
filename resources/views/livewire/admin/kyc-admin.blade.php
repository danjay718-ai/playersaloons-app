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
                @foreach(\App\Shared\Enums\KycStatus::cases() as $status)
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Submissions List (Left 2 columns) -->
        <div class="lg:col-span-2">
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19]">
                    <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-200">KYC Submissions Queue</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] font-bold">
                                <th class="p-4">User</th>
                                <th class="p-4">Document Type</th>
                                <th class="p-4">Submitted At</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/50">
                            @forelse($submissions as $sub)
                                <tr class="hover:bg-slate-900/40">
                                    <td class="p-4">
                                        <span class="block font-semibold text-slate-250">{{ $sub->user->username }}</span>
                                        <span class="block text-[10px] text-slate-500">{{ $sub->user->email }}</span>
                                    </td>
                                    <td class="p-4 text-slate-300 capitalize">
                                        {{ str_replace('_', ' ', $sub->document_type) }}
                                    </td>
                                    <td class="p-4 text-slate-450">
                                        {{ $sub->created_at->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="p-4">
                                        @php
                                            $kycColors = [
                                                'not_submitted' => 'bg-slate-800 text-slate-400 border-slate-700',
                                                'submitted' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20 animate-pulse',
                                                'under_review' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                                'approved' => 'bg-emerald-500/10 text-emerald-450 border-emerald-500/20',
                                                'rejected' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                            ];
                                            $col = $kycColors[$sub->status->value] ?? 'bg-slate-800 text-slate-400 border-slate-750';
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 rounded border text-[9px] font-bold uppercase {{ $col }}">
                                            {{ str_replace('_', ' ', $sub->status->value) }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <button wire:click="selectSubmission({{ $sub->id }})" class="px-3 py-1 bg-slate-800 hover:bg-slate-750 border border-slate-700 text-slate-200 font-bold rounded-lg text-[10px] uppercase tracking-wider">
                                            Review
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-slate-500 italic">No KYC submissions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="mt-4">
                {{ $submissions->links() }}
            </div>
        </div>

        <!-- Audit Trail (Right 1 column) -->
        <div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl p-5 shadow-sm">
                <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-200 mb-4 border-b border-slate-800 pb-3">Compliance Decisions</h4>
                <div class="space-y-4">
                    @forelse($auditTrail as $audit)
                        <div class="border-b border-slate-850 pb-3 last:border-0 last:pb-0 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold uppercase tracking-wider text-[9px] 
                                      {{ $audit->description === 'kyc_approved' ? 'text-emerald-400' : 'text-red-400' }}">
                                    {{ $audit->description === 'kyc_approved' ? 'Approved' : 'Rejected' }}
                                </span>
                                <span class="text-[10px] text-slate-500">{{ $audit->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-slate-300 mt-1 font-medium">
                                User ID: {{ $audit->subject_id }} reviewed by {{ $audit->causer->username ?? 'Staff' }}
                            </p>
                            @if(isset($audit->properties['reason']))
                                <p class="text-slate-500 italic mt-1 text-[11px]">Reason: {{ $audit->properties['reason'] }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-slate-500 text-center italic py-4">No decisions logged yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    @if($showDetailModal && $selectedSubmission)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showDetailModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-3xl w-full overflow-hidden shadow-2xl relative z-10 max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Review KYC Submission</h3>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5">{{ $selectedSubmission->uuid }}</p>
                    </div>
                    <button wire:click="$set('showDetailModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-6 flex-grow text-xs">
                    <!-- User Details Summary -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-[#0b0f19] border border-slate-800 rounded-xl p-4">
                        <div>
                            <span class="text-slate-500 font-medium block">Applicant Name</span>
                            <span class="text-slate-200 font-semibold mt-1 block">{{ $selectedSubmission->user->username }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-medium block">Applicant Email</span>
                            <span class="text-slate-200 font-semibold mt-1 block truncate">{{ $selectedSubmission->user->email }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-medium block">Document Type</span>
                            <span class="text-slate-200 font-semibold mt-1 block capitalize">{{ str_replace('_', ' ', $selectedSubmission->document_type) }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-medium block">Submission Status</span>
                            <span class="inline-block mt-1 px-2 py-0.5 rounded border font-bold uppercase text-[9px]
                                  {{ $selectedSubmission->status->value === 'approved' ? 'bg-emerald-500/10 text-emerald-450 border-emerald-500/20' : ($selectedSubmission->status->value === 'rejected' ? 'bg-red-500/10 text-red-400 border-red-500/20' : 'bg-blue-500/10 text-blue-400 border-blue-500/20') }}">
                                {{ str_replace('_', ' ', $selectedSubmission->status->value) }}
                            </span>
                        </div>
                    </div>

                    <!-- Documents display -->
                    <div>
                        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block mb-3">Submitted ID Documents</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Document Front -->
                            <div class="bg-slate-900 border border-slate-850 p-4 rounded-xl text-center">
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-2">Document Front</span>
                                @if($selectedSubmission->document_front_path)
                                    <div class="relative group bg-[#060810] border border-slate-800 rounded-lg h-48 flex items-center justify-center overflow-hidden">
                                        <img src="/storage/{{ $selectedSubmission->document_front_path }}" class="max-h-full max-w-full object-contain" alt="Front ID">
                                        <a href="/storage/{{ $selectedSubmission->document_front_path }}" target="_blank" class="absolute bottom-2 right-2 p-1.5 bg-slate-950/80 hover:bg-slate-900 text-indigo-400 rounded-lg border border-slate-800/40 opacity-0 group-hover:opacity-100 transition-opacity" title="Open Full Size">
                                            <i data-lucide="external-link" class="w-4 h-4"></i>
                                        </a>
                                    </div>
                                @else
                                    <p class="text-slate-500 italic py-12">No document front uploaded.</p>
                                @endif
                            </div>

                            <!-- Document Back -->
                            <div class="bg-slate-900 border border-slate-850 p-4 rounded-xl text-center">
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-2">Document Back</span>
                                @if($selectedSubmission->document_back_path)
                                    <div class="relative group bg-[#060810] border border-slate-800 rounded-lg h-48 flex items-center justify-center overflow-hidden">
                                        <img src="/storage/{{ $selectedSubmission->document_back_path }}" class="max-h-full max-w-full object-contain" alt="Back ID">
                                        <a href="/storage/{{ $selectedSubmission->document_back_path }}" target="_blank" class="absolute bottom-2 right-2 p-1.5 bg-slate-950/80 hover:bg-slate-900 text-indigo-400 rounded-lg border border-slate-800/40 opacity-0 group-hover:opacity-100 transition-opacity" title="Open Full Size">
                                            <i data-lucide="external-link" class="w-4 h-4"></i>
                                        </a>
                                    </div>
                                @else
                                    <div class="border border-dashed border-slate-800 rounded-lg h-48 flex items-center justify-center text-slate-600">
                                        <span>No document back uploaded</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Past reviewer notes if rejected -->
                    @if($selectedSubmission->status->value === 'rejected' && $selectedSubmission->review_notes)
                        <div class="bg-red-500/5 border border-red-500/15 rounded-lg p-4">
                            <span class="text-[10px] text-red-400 font-bold uppercase tracking-wider">Rejection Reason Notes</span>
                            <p class="text-slate-400 block mt-1 leading-relaxed bg-slate-900/60 p-2.5 rounded border border-slate-800/40">{{ $selectedSubmission->review_notes }}</p>
                            <span class="text-[9px] text-slate-500 mt-1 block">Reviewed by User ID: {{ $selectedSubmission->reviewed_by }}</span>
                        </div>
                    @endif
                </div>

                <div class="px-6 py-4 border-t border-slate-800 bg-[#0b0f19] flex justify-end space-x-3">
                    @if($selectedSubmission->status->value === 'under_review')
                        <button wire:click="openRejectModal" class="bg-red-950 hover:bg-red-900 border border-red-900/50 text-red-400 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Reject
                        </button>
                        <button wire:click="approve" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                            Approve
                        </button>
                    @endif
                    <button wire:click="$set('showDetailModal', false)" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase px-4 py-2.5 rounded-lg">
                        Close Detail
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Reject Submission Reason Modal -->
    @if($showRejectModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" wire:click="$set('showRejectModal', false)"></div>
            <div class="bg-[#0f172a] border border-slate-800 rounded-xl max-w-md w-full overflow-hidden shadow-2xl relative z-10">
                <div class="px-6 py-4 border-b border-slate-800 bg-[#0b0f19] flex justify-between items-center">
                    <h3 class="text-sm font-bold text-red-400 uppercase tracking-wider">Reject KYC Submission</h3>
                    <button wire:click="$set('showRejectModal', false)" class="text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form wire:submit.prevent="reject" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Reason for Rejection</label>
                        <input type="text" wire:model="rejectReason" placeholder="e.g. Unclear document image, wrong format"
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
                            Reject Applicant
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
