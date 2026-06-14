@props(['show', 'title', 'message', 'action'])

<div x-data="{ show: @entangle($show) }" x-show="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4" x-cloak>
    <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click="show = false"></div>
    <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="bg-slate-900 border border-slate-700 rounded-xl p-6 w-full max-w-lg relative shadow-2xl">
        <h3 class="text-lg font-bold text-white mb-2">{{ $title }}</h3>
        <p class="text-sm text-slate-400 mb-6">{{ $message }}</p>
        <div class="flex justify-end gap-3">
            <button @click="show = false" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors">Cancel</button>
            <button wire:click="{{ $action }}" class="px-4 py-2 text-sm font-bold bg-red-600 hover:bg-red-500 text-white rounded-lg transition-colors">Delete Permanently</button>
        </div>
    </div>
</div>
