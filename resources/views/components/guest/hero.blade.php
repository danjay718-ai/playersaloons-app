@props(['hero', 'tournament' => null])
@php
    $rawTitle = trim((string) $hero->title);
    $phrases = preg_split('/(?<=\.)\s+/', $rawTitle, -1, PREG_SPLIT_NO_EMPTY) ?: [$rawTitle];
    if (count($phrases) === 1 && str_contains($rawTitle, ' ')) {
        $words = explode(' ', $rawTitle);
        $lastPhrase = array_pop($words);
        $leadPhrases = [implode(' ', $words)];
    } else {
        $lastPhrase = (string) array_pop($phrases);
        $leadPhrases = $phrases;
    }
    $typewriterWords = array_values(array_unique([$lastPhrase, __('DOMINATE.'), __('GET PAID.'), __('RANK UP.')]));
@endphp
<section class="gr-hero" data-gr-hero>
    <div class="gr-hero-media" wire:ignore x-data="{ playing: false, init() { if (!matchMedia('(prefers-reduced-motion: reduce)').matches) this.$refs.video?.play().then(() => this.playing = true).catch(() => {}); } }">
        @if($hero->media_path)
            <video x-ref="video" muted loop playsinline preload="metadata" poster="/images/guest-highlights.webp" @play="playing = true" @pause="playing = false" aria-label="{{ __('Competitive gaming highlights') }}"><source src="{{ $hero->media_path }}" type="video/mp4"></video>
            <button type="button" class="gr-video-control" @click="playing ? $refs.video.pause() : $refs.video.play()" x-text="playing ? @js(__('Pause video')) : @js(__('Play video'))">{{ __('Play video') }}</button>
        @endif
    </div>
    <div class="gr-hero-glow" aria-hidden="true"></div>
    <div class="gr-wrap gr-hero-stage">
        <div class="gr-hero-copy">
            <p class="gr-eyebrow"><span class="gr-slash" aria-hidden="true">//</span> {{ __('Competitive gaming for cash') }}</p>
            <h1>
                @foreach($leadPhrases as $phrase)
                    <span class="gr-hero-word {{ $loop->iteration === 2 ? 'is-cyan' : 'is-ink' }}">{{ $phrase }}</span>
                @endforeach
                <span class="gr-hero-typed" x-data="grTypewriter(@js($typewriterWords))">
                    <span x-text="display">{{ $lastPhrase }}</span><span class="gr-type-cursor" aria-hidden="true">|</span>
                </span>
            </h1>
            <p class="gr-hero-body">{{ $hero->body }}</p>
            <div class="gr-hero-actions">
                <x-guest.button href="/register">{{ __('Create Account') }}</x-guest.button>
                @if($hero->cta_label && $hero->cta_url)<x-guest.button :href="$hero->cta_url" variant="ghost">{{ __($hero->cta_label) }}</x-guest.button>@endif
            </div>
        </div>
        <x-guest.ticket :tournament="$tournament" />
    </div>
</section>
