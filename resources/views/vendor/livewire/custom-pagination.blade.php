@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between">
        <div class="flex flex-1 justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center rounded-md border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-slate-500">Previous</span>
            @else
                <button wire:click="previousPage" wire:loading.attr="disabled" class="relative inline-flex items-center rounded-md border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-700">Previous</button>
            @endif

            @if ($paginator->hasMorePages())
                <button wire:click="nextPage" wire:loading.attr="disabled" class="relative ml-3 inline-flex items-center rounded-md border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-700">Next</button>
            @else
                <span class="relative ml-3 inline-flex items-center rounded-md border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-slate-500">Next</span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-end gap-2">
            @if (!$paginator->onFirstPage())
                <button wire:click="previousPage" wire:loading.attr="disabled" class="relative inline-flex items-center rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-medium text-slate-300 hover:bg-indigo-950 hover:border-indigo-700 transition-colors">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </button>
            @endif

            @foreach ($paginator->links()->elements[0] as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="relative inline-flex items-center rounded-lg border border-indigo-700 bg-indigo-900 px-4 py-2 text-sm font-medium text-white">{{ $page }}</span>
                @else
                    <button wire:click="gotoPage({{ $page }})" class="relative inline-flex items-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-medium text-slate-400 hover:bg-slate-800 transition-colors">{{ $page }}</button>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button wire:click="nextPage" wire:loading.attr="disabled" class="relative inline-flex items-center rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-medium text-slate-300 hover:bg-indigo-950 hover:border-indigo-700 transition-colors">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
            @endif
        </div>
    </nav>
@endif
