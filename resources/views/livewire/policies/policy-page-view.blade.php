<main class="landing-page-root min-h-screen bg-[#050311] pt-28 text-zinc-100">
    <article class="mx-auto w-full max-w-4xl px-4 pb-20 sm:px-6 lg:px-8">
        <a href="/policies" wire:navigate class="mb-8 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-zinc-500 transition-colors hover:text-cyan-300">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            Policies
        </a>

        <header class="border-b border-zinc-900 pb-8">
            <p class="landing-section-kicker">PlayerSaloons policy</p>
            <h1 class="mt-3 font-orbitron text-4xl font-black uppercase text-white sm:text-5xl">{{ $policy->title }}</h1>
            @if($policy->summary)
                <p class="mt-4 text-base leading-7 text-zinc-400">{{ $policy->summary }}</p>
            @endif
            <p class="mt-6 text-[10px] font-black uppercase tracking-widest text-zinc-600">
                Last updated {{ $policy->updated_at->format('M j, Y') }}
            </p>
        </header>

        <div class="policy-rich-content mt-10 text-sm leading-7 text-zinc-300 sm:text-base">
            {!! $policy->content !!}
        </div>
    </article>

    @include('components.layouts.partials.public-footer')

    <style>
        .policy-rich-content p {
            margin-bottom: 1.5rem;
        }
        .policy-rich-content strong {
            color: #f8fafc;
            font-weight: 800;
        }
        .policy-rich-content em {
            color: #cbd5e1;
        }
        .policy-rich-content a {
            color: #22d3ee;
            text-decoration: underline;
            text-underline-offset: 4px;
        }
        .policy-rich-content ul,
        .policy-rich-content ol {
            margin: 0 0 1.5rem 1.25rem;
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
