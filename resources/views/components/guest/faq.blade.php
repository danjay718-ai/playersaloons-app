@props(['section' => null])

@php
    $title = $section?->title ?: __('Frequently Asked Questions');
    $subtitle = $section?->subtitle ?: __('Got questions? Everything you need to know about competing.');
    $body = $section?->body;
    $items = $section?->activeItems ?? collect();

    $defaultFaqs = [
        [
            'title' => 'How do tournament payouts work?',
            'body' => 'Prizes are automatically credited to your player wallet as soon as the bracket concludes or match results are confirmed. You can withdraw your earnings anytime via supported withdrawal methods.',
        ],
        [
            'title' => 'How do I submit match results?',
            'body' => 'After playing your match against your opponent, navigate to the tournament match room or the Submit Result tab in the tournament details, select Won, Lost, or Draw, and attach your match screenshot proof.',
        ],
        [
            'title' => 'What happens if there is a score dispute?',
            'body' => 'If both players report conflicting scores, our automated system holds the match and flags it for staff review. Admins inspect uploaded screenshots and game server stats to resolve disputes fairly.',
        ],
        [
            'title' => 'Can I cancel my registration before the tournament starts?',
            'body' => 'Yes. You can cancel your tournament entry before the scheduled start time directly from the tournament page. Eligible refunds are credited directly back to your wallet.',
        ],
        [
            'title' => 'Are tournaments free to enter or paid?',
            'body' => 'We offer both Free-to-Play daily tournaments with real prize pools and premium entry-fee tournaments for high-stakes competition.',
        ],
    ];

    $faqList = $items->isNotEmpty() ? $items->map(fn($item) => ['title' => $item->title ?: $item->label, 'body' => $item->body])->all() : $defaultFaqs;
@endphp

<section id="faq" class="gr-section gr-wrap gr-faq my-16 sm:my-24">
    <div class="text-center max-w-3xl mx-auto mb-10 sm:mb-14">
        <p class="text-[10px] sm:text-xs font-black uppercase tracking-[0.25em] text-[#c6ff3d] mb-2 sm:mb-3">
            <span class="text-cyan-400">//</span> {{ __('Knowledge Base') }}
        </p>
        <h2 class="font-orbitron text-2xl sm:text-4xl font-black uppercase text-white tracking-tight">
            {{ $title }}
        </h2>
        @if($subtitle)
            <p class="mt-3 text-xs sm:text-sm text-zinc-400 leading-relaxed max-w-xl mx-auto">
                {{ $subtitle }}
            </p>
        @endif
    </div>

    <div class="max-w-3xl mx-auto space-y-3" x-data="{ openFaq: 0 }">
        @foreach($faqList as $idx => $faq)
            <div class="overflow-hidden rounded-2xl border transition-all duration-200"
                 :class="openFaq === {{ $idx }} ? 'border-cyan-400/40 bg-[#0a1220] shadow-[0_0_24px_rgba(34,230,255,0.08)]' : 'border-white/10 bg-[#0a1220]/60 hover:border-white/20'">
                <button type="button"
                        @click="openFaq = openFaq === {{ $idx }} ? null : {{ $idx }}"
                        class="w-full flex items-center justify-between gap-4 p-5 sm:p-6 text-left transition-colors"
                        :aria-expanded="String(openFaq === {{ $idx }})">
                    <span class="font-orbitron text-sm sm:text-base font-bold text-white tracking-wide"
                          :class="openFaq === {{ $idx }} ? 'text-[#c6ff3d]' : 'text-white'">
                        {{ $faq['title'] }}
                    </span>
                    <span class="shrink-0 flex items-center justify-center w-8 h-8 rounded-xl border border-white/10 bg-white/5 transition-transform duration-200"
                          :class="openFaq === {{ $idx }} ? 'rotate-180 border-[#c6ff3d]/40 text-[#c6ff3d]' : 'text-zinc-400'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </span>
                </button>
                <div x-show="openFaq === {{ $idx }}"
                     x-collapse
                     x-cloak
                     class="px-5 pb-5 sm:px-6 sm:pb-6 pt-0 border-t border-white/5">
                    <p class="mt-3 text-xs sm:text-sm text-zinc-300 leading-relaxed">
                        {{ $faq['body'] }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>
</section>
