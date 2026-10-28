@props(['section'])
<div class="gr-joinbar" x-cloak x-show="pastHero" x-transition.opacity>
    @guest<a class="gr-link" href="/login">{{ __('Sign In') }}</a>@endguest
    @if($section->cta_label && $section->cta_url)<x-guest.button :href="auth()->check() ? route('dashboard') : $section->cta_url">{{ auth()->check() ? __('Dashboard') : $section->cta_label }}</x-guest.button>@endif
</div>
