@php
    $formatPolicySections = function (string $rawContent, string $slug): array {
        $trimmed = trim($rawContent);
        if ($trimmed === '') {
            return [];
        }

        // If HTML headings exist (h2/h3), split by headings; otherwise split by double-newline or <p> blocks
        if (preg_match('/<h[23][^>]*>/i', $trimmed)) {
            $parts = preg_split('/(?=<h[23][^>]*>)/i', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [$trimmed];
        } elseif (str_contains($trimmed, '<p')) {
            preg_match_all('/<p[^>]*>.*?<\/p>/is', $trimmed, $matches);
            $parts = ! empty($matches[0]) ? $matches[0] : [$trimmed];
        } else {
            $parts = preg_split('/\R{2,}/', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [$trimmed];
        }

        $sections = [];
        foreach ($parts as $idx => $part) {
            $num = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
            $secId = 'sec-'.$slug.'-'.($idx + 1);

            if (preg_match('/<h[23][^>]*>(.*?)<\/h[23]>/is', $part, $hMatch)) {
                $label = trim(strip_tags($hMatch[1]));
                $html = $part;
            } else {
                $plain = trim(preg_replace('/\s+/', ' ', strip_tags($part)) ?? '');
                $words = array_slice(explode(' ', $plain), 0, 5);
                $label = trim(implode(' ', $words), ".,;:!?") ?: ('Clause '.$num);
                $html = str_contains($part, '<') ? $part : '<p>'.nl2br(e($part)).'</p>';
            }

            $sections[] = [
                'id' => $secId,
                'num' => $num,
                'label' => \Illuminate\Support\Str::limit($label, 34),
                'html' => $html,
            ];
        }

        return $sections;
    };
@endphp

<main class="landing-page-root gr-public-page min-h-screen bg-[#05080f] pt-10 text-zinc-100">
    <section
        class="mx-auto w-full max-w-6xl px-4 pb-20 sm:px-6 lg:px-8"
        x-data="{
            search: '',
            activeSlug: @js($policies->first()?->slug ?? ''),
            activeSection: '',
            scrollProgress: 0,
            matchesSearch(text) {
                const q = this.search.trim().toLowerCase();
                return !q || text.toLowerCase().includes(q);
            },
            scrollToSection(containerId, sectionId) {
                this.activeSection = sectionId;
                const container = document.getElementById(containerId);
                const target = document.getElementById(sectionId);
                if (container && target) {
                    const top = target.offsetTop - container.offsetTop - 16;
                    container.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
                }
            },
            onReaderScroll(event, sectionIds) {
                const el = event.target;
                const maxScroll = el.scrollHeight - el.clientHeight;
                this.scrollProgress = maxScroll > 0 ? Math.min(100, Math.round((el.scrollTop / maxScroll) * 100)) : 100;
                for (const id of sectionIds) {
                    const sec = document.getElementById(id);
                    if (sec && (sec.offsetTop - el.offsetTop - el.scrollTop) <= 80) {
                        this.activeSection = id;
                    }
                }
            }
        }"
    >
        {{-- Header & Fair-Play Pillars --}}
        <div class="mb-8 flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
            <div>
                <p class="gr-eyebrow"><span class="gr-slash" aria-hidden="true">//</span> GamersRival legal center</p>
                <h1 class="mt-2 font-orbitron text-4xl font-black uppercase text-white sm:text-5xl">Policies</h1>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-[#a8b6c9] sm:text-base">
                    Review the current platform policies for privacy, cookies, refunds, cancellations, and important disclaimers. Select any document below to read it immediately or jump between clauses using the section navigator.
                </p>
            </div>

            {{-- Search Filter --}}
            <div class="relative w-full lg:w-80">
                <input
                    type="search"
                    x-model="search"
                    placeholder="Search policies (e.g. refund, privacy)..."
                    class="w-full rounded-xl border border-white/15 bg-[#0a1220] py-2.5 pl-4 pr-9 text-xs font-semibold text-white placeholder-[#7f8ea3] focus:border-[#22e6ff] focus:outline-none"
                >
                <button
                    type="button"
                    x-show="search.length > 0"
                    @click="search = ''"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-[#a8b6c9] hover:text-white"
                    aria-label="Clear search"
                >
                    ✕
                </button>
            </div>
        </div>

        {{-- Player Protection Highlights --}}
        <div class="mb-8 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-[#c6ff3d]/25 bg-[#0a1220]/85 p-4">
                <p class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-[#c6ff3d]">01 // Fair Play Guarantee</p>
                <p class="mt-1 font-orbitron text-sm font-black uppercase text-white">Transparent Match Rules</p>
                <p class="mt-1 text-xs leading-5 text-[#a8b6c9]">Every entry fee, prize split, and result window is locked before match start.</p>
            </div>
            <div class="rounded-xl border border-[#22e6ff]/25 bg-[#0a1220]/85 p-4">
                <p class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-[#22e6ff]">02 // Player Privacy</p>
                <p class="mt-1 font-orbitron text-sm font-black uppercase text-white">Protected Account Data</p>
                <p class="mt-1 text-xs leading-5 text-[#a8b6c9]">Strict verification and privacy controls safeguard your identity and wallet.</p>
            </div>
            <div class="rounded-xl border border-[#ffd23f]/25 bg-[#0a1220]/85 p-4">
                <p class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-[#ffd23f]">03 // Clear Cancellations</p>
                <p class="mt-1 font-orbitron text-sm font-black uppercase text-white">Automatic Refund Rules</p>
                <p class="mt-1 text-xs leading-5 text-[#a8b6c9]">If a tournament does not meet minimum seating, entry fees return automatically.</p>
            </div>
        </div>

        @if($policies->isNotEmpty())
            <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
                {{-- Left Column: Interactive Policy Navigator --}}
                <div class="flex flex-col gap-3 lg:col-span-4">
                    <div class="flex items-center justify-between px-1 text-[11px] font-extrabold uppercase tracking-[0.16em] text-[#7f8ea3]">
                        <span>Select Document</span>
                        <span>{{ $policies->count() }} Active</span>
                    </div>

                    @foreach($policies as $policy)
                        @php($searchBlob = $policy->title.' '.$policy->summary.' '.strip_tags((string) $policy->content))
                        <div
                            x-show="matchesSearch(@js($searchBlob))"
                            @click="activeSlug = @js($policy->slug); scrollProgress = 0; activeSection = ''"
                            :class="activeSlug === @js($policy->slug)
                                ? 'border-[#c6ff3d]/70 bg-[#0e192d] shadow-[0_0_24px_rgba(198,255,61,0.1)]'
                                : 'border-white/10 bg-[#09101d]/85 hover:border-[#22e6ff]/40 hover:bg-[#0c1626]'"
                            class="landing-card gr-surface-card group cursor-pointer p-4 transition-all"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="rounded-full border border-[#22e6ff]/35 bg-[#22e6ff]/10 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-widest text-[#22e6ff]">Policy</span>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-[#a8b6c9]">{{ __('Last updated :value1', ['value1' => ($policy->updated_at->format('M j, Y'))]) }}</span>
                            </div>

                            <h2 class="mt-2 font-orbitron text-base font-black text-white transition-colors group-hover:text-[#c6ff3d]">{{ $policy->title }}</h2>
                            <p class="mt-1 line-clamp-2 text-xs leading-5 text-[#a8b6c9]">{{ $policy->summary }}</p>

                            <div class="mt-3 flex items-center justify-between border-t border-white/10 pt-2 text-[11px] font-extrabold uppercase tracking-wider">
                                <span
                                    :class="activeSlug === @js($policy->slug) ? 'text-[#c6ff3d]' : 'text-[#7f8ea3]'"
                                    x-text="activeSlug === @js($policy->slug) ? '● Reading' : 'Preview'"
                                ></span>
                                <a
                                    href="/policies/{{ $policy->slug }}"
                                    wire:navigate
                                    @click.stop
                                    class="inline-flex items-center gap-1 text-[#22e6ff] hover:text-[#c6ff3d]"
                                >
                                    <span>Dedicated Page</span>
                                    <span aria-hidden="true">↗</span>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Right Column: Sticky Interactive Document Reader + Right Section Scroll Rail --}}
                <div class="lg:sticky lg:top-24 lg:col-span-8">
                    @foreach($policies as $policy)
                        @php($sections = $formatPolicySections((string) $policy->content, $policy->slug))
                        @php($sectionIds = array_column($sections, 'id'))
                        @php($readerId = 'policy-reader-'.$policy->slug)

                        <article
                            x-show="activeSlug === @js($policy->slug)"
                            x-cloak
                            class="landing-card gr-surface-card overflow-hidden border border-[#22e6ff]/30 bg-[#0a1220]/95 shadow-2xl"
                        >
                            {{-- Top Reader Bar + Live Scroll Progress --}}
                            <div class="border-b border-white/10 bg-[#070d18]">
                                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <span class="h-2 w-2 rounded-full bg-[#c6ff3d]"></span>
                                        <span class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-[#c6ff3d]">Interactive Policy Reader</span>
                                        <span class="text-white/20">·</span>
                                        <span class="font-mono text-[11px] text-[#22e6ff]">{{ count($sections) }} {{ __('sections') }}</span>
                                    </div>
                                    <a
                                        href="/policies/{{ $policy->slug }}"
                                        wire:navigate
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-[#c6ff3d]/50 bg-[#c6ff3d]/10 px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-wider text-[#c6ff3d] transition hover:bg-[#c6ff3d] hover:text-[#05080f]"
                                    >
                                        <span>Open Full Page</span>
                                        <span aria-hidden="true">↗</span>
                                    </a>
                                </div>
                                <div class="h-1 w-full bg-white/5">
                                    <div class="h-full bg-gradient-to-r from-[#c6ff3d] to-[#22e6ff] transition-all duration-150" :style="'width: ' + scrollProgress + '%'"></div>
                                </div>
                            </div>

                            <div class="grid lg:grid-cols-12">
                                {{-- Scrollable Document Content --}}
                                <div
                                    id="{{ $readerId }}"
                                    @scroll.passive="onReaderScroll($event, @js($sectionIds))"
                                    class="policy-rich-content max-h-[540px] overflow-y-auto p-6 text-sm leading-7 text-zinc-200 sm:p-7 lg:col-span-8"
                                >
                                    <p class="text-xs font-extrabold uppercase tracking-widest text-[#22e6ff]">
                                        {{ __('Last updated :value1', ['value1' => ($policy->updated_at->format('M j, Y'))]) }}
                                    </p>
                                    <h3 class="mt-2 font-orbitron text-2xl font-black uppercase text-white sm:text-3xl">{{ $policy->title }}</h3>

                                    @if($policy->summary)
                                        <p class="mt-3 rounded-xl border border-white/10 bg-[#05080f]/70 p-4 text-sm leading-6 text-[#d6e1f0]">
                                            {{ $policy->summary }}
                                        </p>
                                    @endif

                                    <div class="mt-6 space-y-6">
                                        @foreach($sections as $sec)
                                            <section id="{{ $sec['id'] }}" class="scroll-mt-4 rounded-xl border border-white/5 bg-[#05080f]/45 p-4 transition hover:border-cyan-500/25">
                                                <div class="mb-2 flex items-center gap-2">
                                                    <span class="rounded bg-[#22e6ff]/15 px-2 py-0.5 font-mono text-[10px] font-black text-[#22e6ff]">§ {{ $sec['num'] }}</span>
                                                    <span class="font-orbitron text-xs font-bold uppercase tracking-wider text-zinc-300">{{ $sec['label'] }}</span>
                                                </div>
                                                <div class="text-sm leading-7 text-zinc-200">
                                                    {!! $sec['html'] !!}
                                                </div>
                                            </section>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Right-Side Section Scroll Navigator --}}
                                <aside class="border-t border-white/10 bg-[#070c16]/90 p-5 lg:col-span-4 lg:border-l lg:border-t-0">
                                    <div class="flex items-center justify-between">
                                        <p class="font-orbitron text-[10px] font-black uppercase tracking-widest text-[#22e6ff]">On This Policy</p>
                                        <span class="font-mono text-[10px] font-bold text-[#c6ff3d]" x-text="scrollProgress + '%'"></span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-[#7f8ea3]">Jump to any section:</p>

                                    <nav class="mt-3 flex flex-col gap-1.5" aria-label="{{ $policy->title }} sections">
                                        @foreach($sections as $sec)
                                            <button
                                                type="button"
                                                @click="scrollToSection(@js($readerId), @js($sec['id']))"
                                                :class="(activeSection === @js($sec['id']) || (!activeSection && {{ $loop->first ? 'true' : 'false' }}))
                                                    ? 'border-[#c6ff3d]/60 bg-[#c6ff3d]/15 text-[#c6ff3d]'
                                                    : 'border-white/5 bg-[#05080f]/60 text-zinc-400 hover:border-[#22e6ff]/40 hover:text-white'"
                                                class="flex items-center gap-2 rounded-lg border px-2.5 py-2 text-left text-[11px] font-bold transition"
                                            >
                                                <span class="font-mono text-[10px] opacity-75">{{ $sec['num'] }}</span>
                                                <span class="truncate">{{ $sec['label'] }}</span>
                                            </button>
                                        @endforeach
                                    </nav>

                                    <button
                                        type="button"
                                        @click="const c = document.getElementById(@js($readerId)); if (c) c.scrollTo({ top: 0, behavior: 'smooth' });"
                                        class="mt-4 flex w-full items-center justify-center gap-1.5 rounded-lg border border-white/10 bg-[#05080f] px-3 py-2 text-[10px] font-extrabold uppercase tracking-wider text-zinc-400 transition hover:border-[#22e6ff] hover:text-[#22e6ff]"
                                    >
                                        <span>↑ Back to Top of Reader</span>
                                    </button>
                                </aside>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @else
            <div class="landing-card gr-surface-card p-8 text-center text-sm text-[#a8b6c9]">
                No active policy pages are published yet.
            </div>
        @endif
    </section>

    <style>
        .policy-rich-content p {
            margin-bottom: 0.75rem;
        }
        .policy-rich-content p:last-child {
            margin-bottom: 0;
        }
        .policy-rich-content strong {
            color: #f8fafc;
            font-weight: 800;
        }
        .policy-rich-content a {
            color: #22e6ff;
            text-decoration: underline;
            text-underline-offset: 4px;
        }
        .policy-rich-content ul,
        .policy-rich-content ol {
            margin: 0 0 1rem 1.25rem;
            padding-left: 1rem;
        }
        .policy-rich-content ul {
            list-style: disc;
        }
        .policy-rich-content ol {
            list-style: decimal;
        }
        .policy-rich-content li {
            margin-bottom: 0.4rem;
        }
    </style>
</main>
