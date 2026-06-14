<div x-data="{ open: false }" class="relative inline-block text-left">
    <button @click="open = !open" @click.away="open = false" class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors">
        <i data-lucide="more-vertical" class="w-4 h-4"></i>
    </button>
    
    <div x-show="open" 
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         style="display: none;" 
         class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-[#0f172a] ring-1 ring-slate-800 z-50 overflow-hidden divide-y divide-slate-800">
        {{ $slot }}
    </div>
</div>
