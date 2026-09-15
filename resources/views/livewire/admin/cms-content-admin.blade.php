<div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
    <livewire:admin.recoverable-delete resource="content" />

    <aside class="space-y-4">
        <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-200">Content Library</h2>
                    <p class="mt-1 text-xs text-slate-500">Blog posts, news articles, and static pages.</p>
                </div>
                <button type="button" wire:click="createContent('blog')" class="rounded-lg bg-indigo-600 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-indigo-500">
                    New
                </button>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2">
                <button type="button" wire:click="createContent('blog')" class="rounded-lg border border-cyan-500/20 bg-cyan-500/10 px-2 py-2 text-[10px] font-bold uppercase tracking-wider text-cyan-300 hover:bg-cyan-500/15">Blog</button>
                <button type="button" wire:click="createContent('news')" class="rounded-lg border border-indigo-500/20 bg-indigo-500/10 px-2 py-2 text-[10px] font-bold uppercase tracking-wider text-indigo-300 hover:bg-indigo-500/15">News</button>
                <button type="button" wire:click="createContent('page')" class="rounded-lg border border-slate-700 bg-slate-900 px-2 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-300 hover:bg-slate-800">Page</button>
            </div>
        </div>

        <div class="max-h-[calc(100vh-250px)] space-y-2 overflow-y-auto pr-1">
            @forelse($pages as $page)
                <div wire:key="content-row-{{ $page->id }}" class="rounded-xl border p-4 transition-colors {{ $selectedPageId === $page->id ? 'border-indigo-500/40 bg-indigo-500/10' : 'border-slate-800 bg-[#0f172a] hover:border-slate-700' }}">
                    <button type="button" wire:click="editContent({{ $page->id }})" class="block w-full text-left">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-100">{{ $page->localizedTitle() }}</p>
                                <p class="mt-1 truncate font-mono text-[10px] text-slate-500">
                                    @if($page->type === 'blog')
                                        /blog/{{ $page->slug }}
                                    @elseif($page->type === 'news')
                                        /news/{{ $page->slug }}
                                    @else
                                        /pages/{{ $page->slug }}
                                    @endif
                                </p>
                            </div>
                            <span class="shrink-0 rounded border border-slate-700 bg-slate-900 px-2 py-0.5 text-[9px] font-bold uppercase text-slate-300">{{ $page->type }}</span>
                        </div>

                        <p class="mt-3 line-clamp-2 text-xs leading-5 text-slate-500">{{ $page->localizedExcerpt() ?? 'No excerpt' }}</p>
                    </button>

                    <div class="mt-4 flex items-center justify-between gap-2">
                        <div>
                            @if($page->published_at)
                                <span class="rounded border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[9px] font-bold uppercase text-emerald-300">Published</span>
                            @else
                                <span class="rounded border border-slate-700 bg-slate-900 px-2 py-0.5 text-[9px] font-bold uppercase text-slate-400">Draft</span>
                            @endif
                            @if($page->is_featured)
                                <span class="ml-1 rounded border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[9px] font-bold uppercase text-amber-300">Featured</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-1">
                            @if($page->published_at)
                                <button type="button" wire:click="unpublishContent({{ $page->id }})" class="rounded-lg border border-slate-700 bg-slate-900 p-1.5 text-slate-400 hover:text-white" title="Move to draft">
                                    <i data-lucide="archive" class="h-3.5 w-3.5"></i>
                                </button>
                            @else
                                <button type="button" wire:click="publishContent({{ $page->id }})" class="rounded-lg border border-emerald-900/50 bg-emerald-950/40 p-1.5 text-emerald-400 hover:text-white" title="Publish">
                                    <i data-lucide="send" class="h-3.5 w-3.5"></i>
                                </button>
                            @endif
                            <button type="button" wire:click="editContent({{ $page->id }})" class="rounded-lg border border-indigo-900/50 bg-indigo-950/40 p-1.5 text-indigo-400 hover:text-white" title="Edit">
                                <i data-lucide="edit" class="h-3.5 w-3.5"></i>
                            </button>
                            <livewire:admin.recoverable-delete resource="content" :record-id="$page->id" :key="'delete-content-'.$page->id" />
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-slate-800 bg-[#0f172a] p-8 text-center text-sm text-slate-500">
                    No content has been created yet.
                </div>
            @endforelse
        </div>

        <div>{{ $pages->links() }}</div>
    </aside>

    <section class="rounded-xl border border-slate-800 bg-[#0f172a]">
        <form wire:submit.prevent="saveContent" class="space-y-6 p-6">
            @if(session()->has('success'))
                <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-5">
                <div>
                    <h2 class="text-base font-bold uppercase tracking-wider text-slate-100">
                        {{ $isPageEdit ? 'Edit Content' : 'Create Content' }}
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Blog = editorial/guide. News = official update. Page = static evergreen content.
                    </p>
                </div>
                <label class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400">
                    <input type="checkbox" wire:model="pageIsFeatured" class="rounded border-slate-700 bg-slate-900 text-indigo-500">
                    Featured
                </label>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Type</label>
                    <select wire:model="pageType" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                        <option value="blog">Blog Post</option>
                        <option value="news">News Article</option>
                        <option value="page">Static Page</option>
                    </select>
                    @error('pageType') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Locale</label>
                    <select wire:model="pageLocale" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                        <option value="en">English (EN)</option>
                        <option value="es">Español (ES)</option>
                        <option value="tl">Tagalog (TL)</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Slug</label>
                    <input type="text" wire:model="pageSlug" readonly placeholder="season-one-guide" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-400 focus:outline-none opacity-70 cursor-not-allowed">
                    @error('pageSlug') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Title</label>
                <input type="text" wire:model.live.debounce.500ms="pageTitle" placeholder="Article title" class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none">
                @error('pageTitle') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Excerpt</label>
                <textarea wire:model="pageExcerpt" rows="3" placeholder="Short summary shown in listings." class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none"></textarea>
                @error('pageExcerpt') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
            </div>

            <div class="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                <div>
                    <x-forms.image-crop-upload model="featuredImage" label="Featured Image" :width="800" :height="500" :max-mb="2" />
                    <div class="mt-3 flex aspect-[16/10] items-center justify-center overflow-hidden rounded-lg border border-slate-700 bg-slate-900 text-center text-xs text-slate-500">
                        @if($featuredImage)
                            <img src="{{ $featuredImage->temporaryUrl() }}" alt="Selected image" class="h-full w-full object-cover">
                        @elseif($pageFeaturedImagePath)
                            <img src="{{ $pageFeaturedImagePath }}" alt="Current featured image" class="h-full w-full object-cover">
                        @else
                            <span class="px-4">Click to upload image</span>
                        @endif
                    </div>
                    <div wire:loading wire:target="featuredImage" class="mt-2 text-[10px] font-bold uppercase tracking-wider text-indigo-300">Uploading...</div>
                </div>

                <div x-data="{
                    quill: null,
                    ready: false,
                    content: @entangle('pageContent'),
                    setEditorContent(value) {
                        this.content = value || '';

                        if (this.quill) {
                            this.quill.root.innerHTML = this.content;
                        }
                    },
                    syncEditorContent() {
                        if (this.quill) {
                            this.content = this.quill.root.innerHTML;
                        }
                    }
                }"
                     @cms-content-selected.window="setEditorContent(($event.detail && $event.detail.content) ? $event.detail.content : '')"
                     @sync-cms-content.window="syncEditorContent()">
                    <label class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Body</label>
                    <div wire:ignore
                         x-show="ready"
                         x-init="
                            const bootEditor = () => {
                                if (quill) {
                                    return;
                                }

                                if (typeof window.Quill === 'undefined') {
                                    window.setTimeout(bootEditor, 75);

                                    return;
                                }

                                quill = new Quill($refs.editor, {
                                    theme: 'snow',
                                    placeholder: 'Write the content body...',
                                    modules: {
                                        toolbar: [
                                            [{ header: [2, 3, false] }],
                                            ['bold', 'italic', 'underline'],
                                            [{ list: 'ordered' }, { list: 'bullet' }],
                                            ['link', 'blockquote'],
                                            ['clean']
                                        ]
                                    }
                                });

                                quill.root.innerHTML = content || '';
                                quill.root.setAttribute('spellcheck', 'true');

                                quill.on('text-change', () => {
                                    content = quill.root.innerHTML;
                                });

                                ready = true;
                            };

                            bootEditor();
                         "
                         class="overflow-hidden rounded-lg border border-slate-800 bg-slate-900">
                        <div x-ref="editor" class="ql-custom-dark min-h-[360px] text-slate-100"></div>
                    </div>
                    <textarea x-show="! ready"
                              x-model.debounce.500ms="content"
                              rows="14"
                              placeholder="Write the content body..."
                              class="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-3 text-sm text-slate-100 focus:border-indigo-500 focus:outline-none"></textarea>
                    @error('pageContent') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-800 pt-5">
                <button type="button" wire:click="createContent('blog')" class="rounded-lg bg-slate-800 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-200 hover:bg-slate-700">
                    Reset
                </button>
                <button type="submit" @click="window.dispatchEvent(new CustomEvent('sync-cms-content'))" wire:loading.attr="disabled" wire:target="featuredImage" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-white hover:bg-indigo-500 disabled:cursor-wait disabled:opacity-50">
                    <span wire:loading.remove wire:target="featuredImage">Save Content</span><span wire:loading wire:target="featuredImage">Uploading Image...</span>
                </button>
            </div>
        </form>
    </section>
</div>

<style>
    .ql-custom-dark .ql-editor {
        color: #f1f5f9 !important;
        min-height: 360px;
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
