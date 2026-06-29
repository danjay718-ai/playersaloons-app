<div class="grid gap-6 lg:grid-cols-[300px_minmax(0,1fr)]">
    <aside class="space-y-3">
        @foreach($policies as $policy)
            <button type="button"
                    wire:click="selectPolicy({{ $policy->id }})"
                    class="w-full rounded-lg border px-4 py-3 text-left transition-colors {{ $selectedPolicyId === $policy->id ? 'border-indigo-500 bg-indigo-500/10 text-indigo-200' : 'border-slate-800 bg-[#0f172a] text-slate-400 hover:text-slate-200' }}">
                <span class="block text-sm font-bold">{{ $policy->title }}</span>
                <span class="mt-1 block font-mono text-[10px] text-slate-500">/policies/{{ $policy->slug }}</span>
                <span class="mt-2 inline-flex rounded border px-2 py-0.5 text-[9px] font-bold uppercase {{ $policy->is_active && $policy->published_at ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-400' : 'border-slate-700 bg-slate-900 text-slate-500' }}">
                    {{ $policy->is_active && $policy->published_at ? 'Published' : 'Hidden' }}
                </span>
            </button>
        @endforeach
    </aside>

    <section class="rounded-xl border border-slate-800 bg-[#0f172a]">
        <div class="border-b border-slate-800 px-6 py-4">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-200">Policy Content</h3>
            <p class="mt-1 text-xs text-slate-500">Dedicated database-backed legal/policy pages. These are separate from CMS pages.</p>
        </div>

        @if(session()->has('success'))
            <div class="mx-6 mt-6 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">
                {{ session('success') }}
            </div>
        @endif

        @if($selectedPolicyId)
            <form wire:submit.prevent="savePolicy" class="space-y-5 p-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Title</label>
                        <input type="text" wire:model="title" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                        @error('title') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Slug</label>
                        <input type="text" wire:model="slug" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                        @error('slug') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Summary</label>
                        <input type="text" wire:model="summary" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                        @error('summary') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Sort Order</label>
                        <input type="number" wire:model="sortOrder" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                        @error('sortOrder') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex items-end gap-5 pb-2">
                        <label class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400">
                            <input type="checkbox" wire:model="isActive" class="rounded border-slate-700 bg-slate-900 text-indigo-500">
                            Active
                        </label>
                        <label class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400">
                            <input type="checkbox" wire:model="isPublished" class="rounded border-slate-700 bg-slate-900 text-indigo-500">
                            Published
                        </label>
                    </div>
                </div>

                <div wire:ignore
                     x-data="{ quill: null }"
                     @sync-policy-quill.window="$wire.content = quill.root.innerHTML"
                     @policy-content-selected.window="
                        if (quill) {
                            quill.root.innerHTML = $event.detail.content || '';
                        }
                     "
                     x-init="
                        quill = new Quill($refs.editor, {
                            theme: 'snow',
                            placeholder: 'Write policy content...',
                            modules: {
                                toolbar: [['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['link'], ['clean']]
                            }
                        });
                        quill.root.innerHTML = $wire.content;
                        quill.on('text-change', () => {
                            $wire.content = quill.root.innerHTML;
                        });
                     ">
                    <label class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Body Content</label>
                    <div class="rounded-lg border border-slate-800 bg-slate-900">
                        <div x-ref="editor" class="min-h-[300px] border-none text-slate-100 ql-custom-dark"></div>
                    </div>
                    @error('content') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                    <p class="mt-2 text-[10px] text-slate-500">Uses the same rich editor as the tournament wizard. Formatting is stored as HTML.</p>
                </div>

                <div class="flex items-center justify-between border-t border-slate-800 pt-5">
                    <a href="/policies/{{ $slug }}" target="_blank" class="text-xs font-bold uppercase tracking-wider text-slate-500 transition-colors hover:text-cyan-300">
                        View Public Page
                    </a>
                    <button type="submit" @click="window.dispatchEvent(new CustomEvent('sync-policy-quill'))" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white hover:bg-indigo-500">
                        Save Policy
                    </button>
                </div>
            </form>
        @else
            <div class="p-8 text-center text-sm text-slate-500">No policy pages seeded yet.</div>
        @endif
    </section>

    <style>
        .ql-custom-dark .ql-editor {
            color: #f1f5f9 !important;
            min-height: 300px;
        }
        .ql-toolbar.ql-snow {
            border-color: #1e293b !important;
            background: #0f172a !important;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }
        .ql-container.ql-snow {
            border-color: #1e293b !important;
            background: #0b0f19 !important;
            border-bottom-left-radius: 0.5rem;
            border-bottom-right-radius: 0.5rem;
        }
        .ql-snow .ql-stroke {
            stroke: #94a3b8 !important;
        }
        .ql-snow .ql-fill {
            fill: #94a3b8 !important;
        }
        .ql-snow .ql-picker {
            color: #94a3b8 !important;
        }
        .ql-editor.ql-blank::before {
            color: #475569 !important;
            font-style: normal !important;
        }
        .ql-editor {
            font-family: inherit !important;
            font-size: 0.875rem !important;
        }
    </style>
</div>
