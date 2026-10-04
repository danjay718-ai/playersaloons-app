@props([
    'name',
    'label',
    'value' => '',
    'placeholder' => '',
    'height' => 'h-56',
    'scrollControls' => false,
])

{{-- Blade/Alpine Quill editor. V2 remains a standard controller form, without Livewire requests while typing. --}}
<div
    x-data="{
        content: @js((string) $value),
        editor: null,
        init() {
            const boot = () => {
                if (typeof window.Quill === 'undefined') { window.setTimeout(boot, 75); return; }
                this.editor = new Quill(this.$refs.editor, {
                    theme: 'snow',
                    placeholder: @js($placeholder),
                    modules: { toolbar: [
                        [{ font: [] }, { size: ['small', false, 'large', 'huge'] }],
                        [{ header: [1, 2, 3, 4, 5, 6, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ color: [] }, { background: [] }],
                        [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
                        [{ align: [] }], ['link', 'blockquote', 'code-block'], ['clean']
                    ] }
                });
                this.editor.getModule('toolbar').container.style.flex = '0 0 auto';
                Object.assign(this.editor.root.style, { height: '100%', minHeight: '0', overflowY: 'auto' });
                this.editor.root.innerHTML = this.content || '';
                this.editor.root.setAttribute('spellcheck', 'true');
                this.editor.root.setAttribute('aria-label', @js($label));
                this.editor.on('text-change', () => this.content = this.editor.root.innerHTML);
            };
            boot();
        },
        setContent(value) {
            this.content = value || '';
            if (this.editor && this.editor.root.innerHTML !== this.content) this.editor.root.innerHTML = this.content;
        },
        scrollToBottom() {
            if (!this.editor) return;
            this.editor.root.scrollTo({
                top: this.editor.root.scrollHeight,
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
            });
        }
    }"
    @v2-rich-editor.window="if ($event.detail.name === @js($name)) setContent($event.detail.value)"
>
    <div class="mb-2 flex items-center justify-between gap-3">
        <label class="v2-field-label" for="{{ $name }}-editor">{{ $label }}</label>
        @if($scrollControls)
            <button type="button" @click="scrollToBottom()" aria-controls="{{ $name }}-editor" aria-label="{{ __('Scroll to bottom') }}: {{ $label }}" title="{{ __('Scroll to bottom') }}" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-700 bg-slate-950 text-indigo-300 hover:border-indigo-500 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true"><path d="M12 4v14m-6-6 6 6 6-6M5 21h14" /></svg>
            </button>
        @endif
    </div>
    <textarea x-ref="input" x-model="content" name="{{ $name }}" class="hidden"></textarea>
    <div class="flex flex-col overflow-hidden rounded-xl border border-slate-800 bg-slate-950 shadow-inner shadow-black/10 {{ $height }}">
        <div id="{{ $name }}-editor" x-ref="editor" style="flex: 1; min-height: 0; height: 0; overflow: hidden;" class="ql-custom-dark ql-editor-shell text-slate-100"></div>
    </div>
    @error($name)<p class="v2-field-error">{{ $message }}</p>@enderror
</div>
