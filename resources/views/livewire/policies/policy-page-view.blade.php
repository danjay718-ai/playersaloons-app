@php
    $rawContent = trim((string) $policy->content);
    $sections = [];
    if ($rawContent !== '' && preg_match('/<h[23][^>]*>/i', $rawContent)) {
        $parts = preg_split('/(?=<h[23][^>]*>)/i', $rawContent, -1, PREG_SPLIT_NO_EMPTY) ?: [$rawContent];
        foreach ($parts as $idx => $part) {
            $num = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
            $secId = 'clause-'.($idx + 1);
            if (preg_match('/<h[23][^>]*>(.*?)<\/h[23]>/is', $part, $hMatch)) {
                $label = trim(strip_tags($hMatch[1]));
            } else {
                $label = 'Clause '.$num;
            }
            $sections[] = [
                'id' => $secId,
                'num' => $num,
                'label' => \Illuminate\Support\Str::limit($label, 36),
                'html' => $part,
            ];
        }
    }
    $sectionIds = array_column($sections, 'id');
@endphp

<main
    class="landing-page-root gr-public-page min-h-screen bg-[#05080f] pt-10 text-zinc-100"
    x-data="{
        activeSection: @js($sectionIds[0] ?? ''),
        scrollProgress: 0,
        sectionIds: @js($sectionIds),
        jumpTo(id) {
            this.activeSection = id;
            const el = document.getElementById(id);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },
        updateOnScroll() {
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            this.scrollProgress = docHeight > 0 ? Math.min(100, Math.round((window.scrollY / docHeight) * 100)) : 100;
            for (const id of this.sectionIds) {
                const el = document.getElementById(id);
                if (el && el.getBoundingClientRect().top <= 160) {
                    this.activeSection = id;
                }
            }
        }
    }"
    @scroll.window.passive="updateOnScroll()"
>
    <div class="mx-auto w-full max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
        <a href="/policies" wire:navigate class="mb-8 inline-flex items-center gap-2 rounded-full border border-white/15 bg-[#0e1726] px-4 py-2 text-xs font-extrabold uppercase tracking-widest text-[#a8b6c9] transition-colors hover:border-[#c6ff3d] hover:text-[#c6ff3d]">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            Policies
        </a>

        <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
            {{-- Left Column: Sticky Policy Switcher Sidebar --}}
            <aside class="lg:sticky lg:top-24 lg:col-span-3">
                <div class="landing-card gr-surface-card p-5">
                    <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-[#22e6ff]">Legal Documents</p>
                    <p class="mt-1 text-xs text-[#a8b6c9]">Switch between platform policies</p>

                    <nav class="mt-4 flex flex-col gap-2" aria-label="Policy navigation">
                        @foreach(($allPolicies ?? []) as $navPolicy)
                            @php($isCurrent = $navPolicy->slug === $policy->slug)
                            <a
                                href="/policies/{{ $navPolicy->slug }}"
                                wire:navigate
                                class="flex items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 text-xs font-extrabold transition-all {{ $isCurrent ? 'border-[#c6ff3d] bg-[#c6ff3d]/10 text-[#c6ff3d]' : 'border-white/10 bg-[#05080f]/60 text-zinc-300 hover:border-[#22e6ff]/40 hover:text-white' }}"
                            >
                                <span class="truncate">{{ $navPolicy->title }}</span>
                                <span aria-hidden="true">{{ $isCurrent ? '●' : '→' }}</span>
                            </a>
                        @endforeach
                    </nav>
                </div>
            </aside>

            {{-- Center Column: Main Policy Document with Section Anchors --}}
            <article class="lg:col-span-6">
                <header class="rounded-2xl border border-white/10 bg-[#0a1220]/90 p-6 sm:p-8">
                    <p class="gr-eyebrow"><span class="gr-slash" aria-hidden="true">//</span> GamersRival policy</p>
                    <h1 class="mt-3 font-orbitron text-3xl font-black uppercase text-white sm:text-4xl">{{ $policy->title }}</h1>
                    @if($policy->summary)
                        <p class="mt-4 text-base leading-7 text-[#a8b6c9]">{{ $policy->summary }}</p>
                    @endif
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4 text-xs font-extrabold uppercase tracking-widest text-[#22e6ff]">
                        <span>{{ __('Last updated :value1', ['value1' => ($policy->updated_at->format('M j, Y'))]) }}</span>
                        <span class="text-[#c6ff3d]">{{ count($sections) }} {{ __('Sections') }}</span>
                    </div>
                </header>

                <div class="landing-card gr-surface-card policy-rich-content mt-6 space-y-6 p-6 text-sm leading-7 text-zinc-200 sm:p-8 sm:text-base">
                    @forelse($sections as $sec)
                        <section
                            id="{{ $sec['id'] }}"
                            class="scroll-mt-28 rounded-2xl border border-white/10 bg-[#05080f]/55 p-5 transition-colors hover:border-[#22e6ff]/35"
                        >
                            <div class="mb-3 flex items-center gap-2.5 border-b border-white/5 pb-2.5">
                                <span class="rounded bg-[#22e6ff]/15 px-2 py-0.5 font-mono text-[10px] font-black text-[#22e6ff]">§ {{ $sec['num'] }}</span>
                                <h2 class="font-orbitron text-xs font-black uppercase tracking-wider text-white sm:text-sm">{{ $sec['label'] }}</h2>
                            </div>
                            <div class="text-sm leading-7 text-zinc-200 sm:text-base">
                                {!! $sec['html'] !!}
                            </div>
                        </section>
                    @empty
                        {!! $policy->content !!}
                    @endforelse
                </div>
            </article>

            {{-- Right Column: Sticky "On This Page" Section Scroll Navigator --}}
            <aside class="lg:sticky lg:top-24 lg:col-span-3">
                <div class="landing-card gr-surface-card p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-[#c6ff3d]">On This Page</p>
                        <span class="font-mono text-xs font-bold text-[#22e6ff]" x-text="scrollProgress + '%'"></span>
                    </div>
                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                        <div class="h-full bg-gradient-to-r from-[#c6ff3d] to-[#22e6ff] transition-all duration-150" :style="'width: ' + scrollProgress + '%'"></div>
                    </div>
                    <p class="mt-2.5 text-xs text-[#a8b6c9]">Jump to any section:</p>

                    <nav class="mt-3 flex flex-col gap-1.5" aria-label="On this page sections">
                        @foreach($sections as $sec)
                            <button
                                type="button"
                                @click="jumpTo(@js($sec['id']))"
                                :class="activeSection === @js($sec['id'])
                                    ? 'border-[#c6ff3d]/70 bg-[#c6ff3d]/15 text-[#c6ff3d]'
                                    : 'border-white/10 bg-[#05080f]/60 text-zinc-400 hover:border-[#22e6ff]/40 hover:text-white'"
                                class="flex items-center gap-2.5 rounded-xl border px-3 py-2 text-left text-xs font-bold transition-all"
                            >
                                <span class="font-mono text-[10px] opacity-75">{{ $sec['num'] }}</span>
                                <span class="truncate">{{ $sec['label'] }}</span>
                            </button>
                        @endforeach
                    </nav>

                    <button
                        type="button"
                        @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                        class="mt-4 flex w-full items-center justify-center gap-1.5 rounded-xl border border-white/15 bg-[#05080f] px-3 py-2.5 text-xs font-extrabold uppercase tracking-wider text-zinc-300 transition hover:border-[#c6ff3d] hover:text-[#c6ff3d]"
                    >
                        <span>↑ Back to Top</span>
                    </button>
                </div>
            </aside>
        </div>
    </div>

    <style>
        .policy-rich-content p {
            margin-bottom: 1rem;
        }
        .policy-rich-content p:last-child {
            margin-bottom: 0;
        }
        .policy-rich-content strong {
            color: #f8fafc;
            font-weight: 800;
        }
        .policy-rich-content em {
            color: #cbd5e1;
        }
        .policy-rich-content a {
            color: #22e6ff;
            text-decoration: underline;
            text-underline-offset: 4px;
        }
        .policy-rich-content ul,
        .policy-rich-content ol {
            margin: 0 0 1.25rem 1.25rem;
            padding-left: 1rem;
        }
        .policy-rich-content ul {
            list-style: disc;
        }
        .policy-rich-content ol {
            list-style: decimal;
        }
        .policy-rich-content li {
            margin-bottom: 0.5rem;
        }
    </style>
</main>
