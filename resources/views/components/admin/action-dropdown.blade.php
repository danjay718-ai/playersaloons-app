<div x-data="{
    open: false,
    top: 0,
    left: 0,
    toggle() {
        this.open = !this.open;
        if (!this.open) return;
        const trigger = this.$refs.trigger.getBoundingClientRect();
        this.left = Math.max(8, Math.min(trigger.right - 224, window.innerWidth - 232));
        this.top = trigger.bottom + 8;
        this.$nextTick(() => {
            const height = this.$refs.menu.offsetHeight;
            if (this.top + height > window.innerHeight - 8) {
                this.top = Math.max(8, trigger.top - height - 8);
            }
        });
    }
}" x-on:keydown.escape.window="open = false" x-on:resize.window="open = false" x-on:scroll.window="open = false" class="relative inline-block text-left">
    <button type="button" x-ref="trigger" @click.stop="toggle()" x-bind:aria-expanded="open" aria-label="Actions" aria-haspopup="true" class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors">
        <i data-lucide="more-vertical" class="w-4 h-4"></i>
    </button>
    
    <template x-teleport="body">
    <div x-show="open" x-ref="menu" @click.outside="open = false" x-bind:style="{ top: top + 'px', left: left + 'px' }"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         style="display: none;" 
         class="theme-admin fixed z-50 max-h-[calc(100vh-1rem)] w-56 overflow-y-auto rounded-md bg-[#0f172a] text-left shadow-lg ring-1 ring-slate-800 divide-y divide-slate-800">
        {{ $slot }}
    </div>
    </template>
</div>
