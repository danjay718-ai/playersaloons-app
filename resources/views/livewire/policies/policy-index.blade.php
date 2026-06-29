<main class="landing-page-root min-h-screen bg-[#050311] pt-28 text-zinc-100">
    <section class="mx-auto w-full max-w-6xl px-4 pb-20 sm:px-6 lg:px-8">
        <div class="mb-10">
            <p class="landing-section-kicker">PlayerSaloons legal center</p>
            <h1 class="mt-3 font-orbitron text-4xl font-black uppercase text-white sm:text-5xl">Policies</h1>
            <p class="mt-4 max-w-3xl text-sm leading-7 text-zinc-400 sm:text-base">
                Review the current platform policies for privacy, cookies, refunds, cancellations, and important disclaimers.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @forelse($policies as $policy)
                <a href="/policies/{{ $policy->slug }}" wire:navigate class="landing-card group block p-6 transition-transform hover:-translate-y-1">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-cyan-300">Policy</p>
                            <h2 class="mt-2 text-xl font-black text-white">{{ $policy->title }}</h2>
                        </div>
                        <i data-lucide="arrow-up-right" class="h-5 w-5 shrink-0 text-zinc-500 transition-colors group-hover:text-cyan-300"></i>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-zinc-400">{{ $policy->summary }}</p>
                    <p class="mt-5 text-[10px] font-black uppercase tracking-widest text-zinc-600">
                        Last updated {{ $policy->updated_at->format('M j, Y') }}
                    </p>
                </a>
            @empty
                <div class="landing-card p-8 text-center text-sm text-zinc-500 md:col-span-2">
                    No active policy pages are published yet.
                </div>
            @endforelse
        </div>
    </section>

    @include('components.layouts.partials.public-footer')
</main>
