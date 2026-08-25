<main class="landing-page-root min-h-screen bg-[#050311] pt-28 text-zinc-100">
    <section class="mx-auto w-full max-w-6xl px-4 pb-20 sm:px-6 lg:px-8">
        <div class="mb-10 max-w-3xl">
            <p class="landing-section-kicker">PlayerSaloons</p>
            <h1 class="mt-3 font-orbitron text-4xl font-black uppercase text-white sm:text-5xl">{{ $title }}</h1>
            <p class="mt-4 text-sm leading-7 text-zinc-400 sm:text-base">{{ $subtitle }}</p>
        </div>

        <article class="landing-card relative overflow-hidden p-6 sm:p-10">
            <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-cyan-400/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-20 -left-20 h-48 w-48 rounded-full bg-violet-500/10 blur-3xl"></div>
            <div class="relative prose prose-invert max-w-none prose-headings:font-orbitron prose-headings:font-black prose-headings:uppercase prose-headings:tracking-wide prose-p:text-zinc-300 prose-p:leading-7 prose-a:text-cyan-300 hover:prose-a:text-cyan-200">
                {!! $body !!}
            </div>
        </article>
    </section>
</main>
