@props([
    'name',
    'label',
    'value' => '',
    'placeholder' => '',
    'height' => 'h-56',
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
                this.editor.root.innerHTML = this.content || '';
                this.editor.root.setAttribute('spellcheck', 'true');
                this.editor.on('text-change', () => this.content = this.editor.root.innerHTML);
            };
            boot();
        },
        setContent(value) {
            this.content = value || '';
            if (this.editor && this.editor.root.innerHTML !== this.content) this.editor.root.innerHTML = this.content;
        }
    }"
    @v2-rich-editor.window="if ($event.detail.name === @js($name)) setContent($event.detail.value)"
>
    <label class="v2-field-label" for="{{ $name }}-editor">{{ $label }}</label>
    <textarea x-ref="input" x-model="content" name="{{ $name }}" class="hidden"></textarea>
    <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-950 shadow-inner shadow-black/10">
        <div id="{{ $name }}-editor" x-ref="editor" class="ql-custom-dark ql-editor-shell {{ $height }} overflow-hidden text-slate-100"></div>
    </div>
    @error($name)<p class="v2-field-error">{{ $message }}</p>@enderror
</div>
