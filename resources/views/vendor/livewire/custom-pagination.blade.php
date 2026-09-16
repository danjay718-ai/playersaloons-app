@php
    $livewire = $livewire ?? true;
    $pageName = $paginator->getPageName();
@endphp
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between">
        <div class="flex flex-1 justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="relative inline-flex items-center rounded-md border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-slate-500">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" @if($livewire) wire:click.prevent="previousPage('{{ $pageName }}')" wire:loading.class="pointer-events-none opacity-50" @endif class="relative inline-flex items-center rounded-md border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-700">Previous</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" @if($livewire) wire:click.prevent="nextPage('{{ $pageName }}')" wire:loading.class="pointer-events-none opacity-50" @endif class="relative ml-3 inline-flex items-center rounded-md border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-700">Next</a>
            @else
                <span aria-disabled="true" class="relative ml-3 inline-flex items-center rounded-md border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-slate-500">Next</span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:flex-wrap sm:items-center sm:justify-end gap-2">
            @if (!$paginator->onFirstPage())
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page" @if($livewire) wire:click.prevent="previousPage('{{ $pageName }}')" wire:loading.class="pointer-events-none opacity-50" @endif class="relative inline-flex items-center rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-medium text-slate-300 hover:bg-indigo-950 hover:border-indigo-700 transition-colors">
                    <i data-lucide="chevron-left" class="w-4 h-4" aria-hidden="true"></i>
                </a>
            @endif

            @foreach ($elements ?? [] as $element)
                @if (is_string($element))
                    <span aria-disabled="true" class="px-2 text-sm text-slate-500">{{ $element }}</span>
                @elseif (is_array($element))
                @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span aria-current="page" class="relative inline-flex items-center rounded-lg border border-indigo-700 bg-indigo-900 px-4 py-2 text-sm font-medium text-white">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}" @if($livewire) wire:click.prevent="gotoPage({{ $page }}, '{{ $pageName }}')" wire:loading.class="pointer-events-none opacity-50" @endif class="relative inline-flex items-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-medium text-slate-400 hover:bg-slate-800 transition-colors">{{ $page }}</a>
                @endif
                @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page" @if($livewire) wire:click.prevent="nextPage('{{ $pageName }}')" wire:loading.class="pointer-events-none opacity-50" @endif class="relative inline-flex items-center rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-medium text-slate-300 hover:bg-indigo-950 hover:border-indigo-700 transition-colors">
                    <i data-lucide="chevron-right" class="w-4 h-4" aria-hidden="true"></i>
                </a>
            @endif
        </div>
    </nav>
@endif
