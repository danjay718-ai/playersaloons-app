@props(['section' => null])
@php($section ??= \App\Modules\CMS\Models\LandingSection::query()->where('key', 'cta')->where('is_active', true)->first())
@if($section)
<section class="gr-cta gr-wrap gr-reveal">
    <div>
        <h2>{{ $section->title }}</h2>
        <p>{{ $section->body }}</p>
    </div>
    <div class="gr-cta-actions">
        @if($section->cta_label && $section->cta_url)<x-guest.button :href="auth()->check() ? route('dashboard') : $section->cta_url">{{ auth()->check() ? __('Dashboard') : $section->cta_label }}</x-guest.button>@endif
        @guest<a class="gr-link" href="/login">{{ __('Sign In') }}</a>@endguest
        <button type="button" class="pwa-install-btn gr-install" data-pwa-install-desktop data-pwa-install-mobile><i data-lucide="download" aria-hidden="true"></i><span data-pwa-install-label>{{ __('Install App') }}</span></button>
    </div>
</section>

@endif
