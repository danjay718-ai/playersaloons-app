<div
    class="theme-switcher {{ $variant === 'cards' ? 'w-full' : 'relative' }}"
    x-data="{
        open: false,
        current: @entangle('theme'),
        choose(theme, metaColor) {
            const previous = this.current;
            this.current = theme;
            document.documentElement.dataset.theme = theme;
            document.querySelector('meta[name=theme-color]')?.setAttribute('content', metaColor);
            this.$wire.setTheme(theme).catch(() => {
                this.current = previous;
                document.documentElement.dataset.theme = previous;
            });
            this.open = false;
        },
    }"
    @theme-preference-updated.window="
        current = $event.detail.theme;
        document.documentElement.dataset.theme = current;
        document.querySelector('meta[name=theme-color]')?.setAttribute('content', $event.detail.metaColor);
    "
    @click.outside="open = false"
>
    @if($variant === 'cards')
        <div class="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Color theme">
            @foreach($themes as $option)
                <button
                    type="button"
                    role="radio"
                    :aria-checked="String(current === '{{ $option->value }}')"
                    @click="choose('{{ $option->value }}', '{{ $option->metaColor() }}')"
                    class="theme-choice-card"
                    :class="current === '{{ $option->value }}' ? 'theme-choice-card-active' : ''"
                    wire:loading.attr="disabled"
                    wire:target="setTheme"
                >
                    <span class="flex items-center gap-1.5" aria-hidden="true">
                        @foreach($option->swatches() as $swatch)
                            <span class="h-5 w-5 rounded-full border border-black/10 shadow-sm" style="background: {{ $swatch }}"></span>
                        @endforeach
                    </span>
                    <span class="mt-4 block text-sm font-black theme-text-strong">{{ $option->label() }}</span>
                    <span class="mt-1 block text-xs leading-5 theme-text-muted">{{ $option->description() }}</span>
                    <span x-show="current === '{{ $option->value }}'" class="mt-3 inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider theme-accent-text"><i data-lucide="check" class="h-3.5 w-3.5"></i>Selected</span>
                </button>
            @endforeach
        </div>
        @error('theme')<p class="mt-3 text-xs font-semibold text-red-500">{{ $message }}</p>@enderror
    @else
        <button
            type="button"
            @click="open = !open"
            :aria-expanded="String(open)"
            aria-label="Choose color theme"
            title="Choose color theme"
            class="theme-switcher-trigger"
        >
            <i data-lucide="palette" class="h-4 w-4"></i>
            <span class="hidden lg:inline">Theme</span>
        </button>
        <div x-show="open" x-cloak x-transition.origin.top.right class="theme-switcher-menu">
            <p class="px-3 pb-2 pt-1 text-[9px] font-black uppercase tracking-[0.2em] theme-text-muted">Appearance</p>
            @foreach($themes as $option)
                <button
                    type="button"
                    @click="choose('{{ $option->value }}', '{{ $option->metaColor() }}')"
                    class="theme-switcher-option"
                    :class="current === '{{ $option->value }}' ? 'theme-switcher-option-active' : ''"
                >
                    <span class="flex shrink-0 items-center -space-x-1" aria-hidden="true">
                        @foreach($option->swatches() as $swatch)
                            <span class="h-4 w-4 rounded-full border border-black/10" style="background: {{ $swatch }}"></span>
                        @endforeach
                    </span>
                    <span class="min-w-0 flex-1 text-left text-xs font-bold">{{ $option->label() }}</span>
                    <i x-show="current === '{{ $option->value }}'" data-lucide="check" class="h-3.5 w-3.5 theme-accent-text"></i>
                </button>
            @endforeach
            @error('theme')<p class="px-3 py-2 text-[10px] font-semibold text-red-500">{{ $message }}</p>@enderror
        </div>
    @endif
</div>
