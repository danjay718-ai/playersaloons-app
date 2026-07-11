<div class="space-y-6">
    @if (session('success'))
        <div class="flex items-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            <i data-lucide="check-circle" class="mr-2 h-4 w-4"></i>
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="flex items-center rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
            <i data-lucide="alert-circle" class="mr-2 h-4 w-4"></i>
            {{ session('error') }}
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach(['new' => 'New', 'in_review' => 'In review', 'resolved' => 'Resolved', 'archived' => 'Archived'] as $value => $label)
            <button type="button" wire:click="$set('status', '{{ $value }}')"
                class="rounded-xl border p-4 text-left transition {{ $status === $value ? ($statusStyles[$value] ?? '') : 'border-slate-800 bg-slate-950/60 text-slate-400 hover:border-slate-700' }}">
                <span class="text-[11px] font-bold uppercase tracking-wider">{{ $label }}</span>
                <span class="mt-1 block text-2xl font-extrabold text-white">{{ (int) ($statusCounts[$value] ?? 0) }}</span>
            </button>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-[1fr_380px]">
        <section class="rounded-xl border border-slate-800 bg-slate-950/60">
            <div class="grid gap-3 border-b border-slate-800 p-4 md:grid-cols-[1fr_160px_180px]">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name, email, or subject"
                    class="rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none">

                <select wire:model.live="status"
                    class="rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none">
                    <option value="">All statuses</option>
                    <option value="new">New</option>
                    <option value="in_review">In review</option>
                    <option value="resolved">Resolved</option>
                    <option value="archived">Archived</option>
                </select>

                <select wire:model.live="category"
                    class="rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none">
                    <option value="">All categories</option>
                    @foreach($categories as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="divide-y divide-slate-800">
                @forelse($inquiries as $inquiry)
                    <button type="button" wire:click="selectInquiry({{ $inquiry->id }})"
                        class="block w-full px-4 py-4 text-left transition hover:bg-slate-900/70 {{ $selectedId === $inquiry->id ? 'bg-indigo-950/30' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-100">{{ $inquiry->subject }}</span>
                                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $statusStyles[$inquiry->status] ?? 'border-slate-700 text-slate-400' }}">{{ str_replace('_', ' ', $inquiry->status) }}</span>
                                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $categoryStyles[$inquiry->category] ?? 'border-slate-700 text-slate-400' }}">{{ $categories[$inquiry->category] ?? $inquiry->category }}</span>
                                </div>
                                <p class="mt-1 truncate text-xs text-slate-400">{{ $inquiry->name }} · {{ $inquiry->email }}</p>
                                <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{{ $inquiry->message }}</p>
                            </div>
                            <span class="shrink-0 text-[11px] text-slate-500">{{ $inquiry->created_at?->diffForHumans() }}</span>
                        </div>
                    </button>
                @empty
                    <div class="px-4 py-10 text-center text-sm text-slate-500">No contact inquiries found.</div>
                @endforelse
            </div>

            <div class="border-t border-slate-800 p-4">
                {{ $inquiries->links() }}
            </div>
        </section>

        <aside class="rounded-xl border border-slate-800 bg-slate-950/60 p-5">
            @if($selectedInquiry)
                <div class="space-y-5">
                    <div>
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider {{ $categoryStyles[$selectedInquiry->category] ?? 'border-slate-700 text-slate-400' }}">{{ $categories[$selectedInquiry->category] ?? $selectedInquiry->category }}</span>
                        <h3 class="mt-1 text-lg font-bold text-white">{{ $selectedInquiry->subject }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ $selectedInquiry->created_at?->format('M d, Y h:i A') }}</p>
                        <span class="mt-3 inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider {{ $statusStyles[$selectedInquiry->status] ?? 'border-slate-700 text-slate-300' }}">
                            {{ str_replace('_', ' ', $selectedInquiry->status) }}
                        </span>
                    </div>

                    <div class="rounded-lg border border-slate-800 bg-slate-900/50 p-4 text-sm leading-6 text-slate-300">
                        {{ $selectedInquiry->message }}
                    </div>

                    <div class="grid gap-2 text-xs text-slate-400">
                        <div><span class="font-semibold text-slate-300">Name:</span> {{ $selectedInquiry->name }}</div>
                        <div><span class="font-semibold text-slate-300">Email:</span> {{ $selectedInquiry->email }}</div>
                        <div><span class="font-semibold text-slate-300">Account:</span> {{ $selectedInquiry->user?->username ?? 'Guest submission' }}</div>
                        @if($selectedInquiry->resolver)
                            <div><span class="font-semibold text-slate-300">Resolved by:</span> {{ $selectedInquiry->resolver->username }}</div>
                        @endif
                    </div>

                    <a href="mailto:{{ $selectedInquiry->email }}?subject={{ rawurlencode('Re: '.$selectedInquiry->subject) }}"
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-indigo-500/30 bg-indigo-500/10 px-3 py-2.5 text-xs font-semibold text-indigo-200 hover:bg-indigo-500/20">
                        <i data-lucide="mail" class="h-4 w-4"></i>
                        Reply by email
                    </a>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-400">Internal notes</label>
                        <textarea wire:model="adminNotes" rows="6"
                            class="mt-2 block w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 focus:outline-none"></textarea>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-3">
                        <button type="button" wire:click="saveNotes" wire:loading.attr="disabled" wire:target="saveNotes"
                            class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="saveNotes">Save Notes</span>
                            <span wire:loading wire:target="saveNotes">Saving...</span>
                        </button>
                        <button type="button" wire:click="markResolved" wire:loading.attr="disabled" wire:target="markResolved"
                            class="rounded-lg border border-emerald-500/30 bg-emerald-600/20 px-3 py-2 text-xs font-semibold text-emerald-200 hover:bg-emerald-600/30 disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="markResolved">Resolve</span>
                            <span wire:loading wire:target="markResolved">Resolving...</span>
                        </button>
                        <button type="button" wire:click="archive" wire:loading.attr="disabled" wire:target="archive"
                            class="rounded-lg border border-amber-500/30 bg-amber-600/20 px-3 py-2 text-xs font-semibold text-amber-200 hover:bg-amber-600/30 disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="archive">Archive</span>
                            <span wire:loading wire:target="archive">Archiving...</span>
                        </button>
                    </div>
                </div>
            @else
                <div class="flex min-h-[320px] flex-col items-center justify-center text-center text-slate-500">
                    <i data-lucide="inbox" class="mb-4 h-10 w-10 text-slate-700"></i>
                    <p class="text-sm font-semibold text-slate-400">Select an inquiry</p>
                    <p class="mt-1 text-xs">Open a message to review details and add internal notes.</p>
                </div>
            @endif
        </aside>
    </div>
</div>
