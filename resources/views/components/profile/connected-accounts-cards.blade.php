@props([
    'playStrategyAccount' => null,
    'googleAccount' => null,
    'showHeading' => true,
])

@php
    $showPlayStrategy = (bool) config('features.playstrategy.enabled');
    $showGoogle = (bool) config('features.google_auth.enabled');
    $linkedCount = ($showPlayStrategy && $playStrategyAccount ? 1 : 0) + ($showGoogle && $googleAccount ? 1 : 0);
    $totalCount = ($showPlayStrategy ? 1 : 0) + ($showGoogle ? 1 : 0);
    $playStrategyTokenUrl = app(\App\Modules\Match\Services\PlayStrategyClient::class)->buildPersonalTokenUrl(config('app.name', 'GamersRival'));
@endphp

@if($showPlayStrategy || $showGoogle)
    <section class="profile-connections-section" aria-label="{{ __('Connected Accounts') }}">
        @if($showHeading)
            <div class="profile-connections-head">
                <div>
                    <h3 class="profile-connections-title">{{ __('Connected Accounts') }}</h3>
                    <p class="profile-connections-subtitle">{{ __('Link external accounts for automated board matches and one-click sign-in.') }}</p>
                </div>
                <span class="profile-connections-count">{{ $linkedCount }} / {{ $totalCount }} {{ __('Linked') }}</span>
            </div>
        @endif

        <div class="profile-connections-grid {{ $totalCount === 1 ? 'is-single' : '' }}">
            @if($showPlayStrategy)
                <article class="profile-connect-card {{ $playStrategyAccount ? 'is-connected' : '' }}">
                    <div class="profile-connect-top">
                        <div class="profile-connect-brand">
                            <span class="profile-connect-icon profile-connect-icon-playstrategy" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none">
                                    <path d="M5 19h14M6.5 16h11l-1-7.5L19 6l-3 1.5L12 4 8 7.5 5 6l2.5 2.5L6.5 16Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="profile-connect-name">{{ __('PlayStrategy') }}</p>
                                <p class="profile-connect-meta">{{ __('Card & Board Games') }}</p>
                            </div>
                        </div>

                        <span class="profile-connect-status {{ $playStrategyAccount ? 'is-linked' : 'is-unlinked' }}">
                            <span class="profile-connect-dot" aria-hidden="true"></span>
                            {{ $playStrategyAccount ? __('Connected') : __('Not linked') }}
                        </span>
                    </div>

                    <div class="profile-connect-body">
                        @if($playStrategyAccount)
                            <p class="profile-connect-identity" translate="no">{{ '@'.$playStrategyAccount->external_username }}</p>
                            <p class="profile-connect-desc">{{ __('Verified for automated Chess, Backgammon, Draughts, and Othello matches.') }}</p>
                        @else
                            <p class="profile-connect-desc">{{ __('Required to join PlayStrategy tournaments with live boards and automatic result sync.') }}</p>
                        @endif
                    </div>

                    <div class="profile-connect-actions" x-data="{ openTokenForm: @js($errors->has('playstrategy_token')) }">
                        @if($playStrategyAccount)
                            <form method="POST" action="{{ route('playstrategy.oauth.disconnect') }}" class="profile-connect-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="profile-connect-btn profile-connect-btn-danger">
                                    {{ __('Disconnect PlayStrategy') }}
                                </button>
                            </form>
                        @else
                            <button
                                type="button"
                                x-show="!openTokenForm"
                                @click="openTokenForm = true"
                                class="profile-connect-btn profile-connect-btn-primary"
                            >
                                <span>{{ __('Connect PlayStrategy') }}</span>
                                <span aria-hidden="true">↗</span>
                            </button>

                            <div x-show="openTokenForm" x-cloak class="profile-connect-token-box">
                                <div class="profile-connect-token-steps">
                                    <p>{{ __('1. Create a personal OAuth token on PlayStrategy (challenge:write is pre-selected):') }}</p>
                                    <a
                                        href="{{ $playStrategyTokenUrl }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="profile-connect-btn profile-connect-btn-secondary"
                                    >
                                        <span>{{ __('Open PlayStrategy Token Page') }}</span>
                                        <span aria-hidden="true">↗</span>
                                    </a>
                                </div>

                                <form method="POST" action="{{ route('playstrategy.oauth.token') }}" class="profile-connect-token-form">
                                    @csrf
                                    <label for="playstrategy_token_{{ $showHeading ? 'desktop' : 'mobile' }}" class="profile-connect-token-label">
                                        {{ __('2. Paste your PlayStrategy OAuth token') }}
                                    </label>
                                    <input
                                        id="playstrategy_token_{{ $showHeading ? 'desktop' : 'mobile' }}"
                                        name="playstrategy_token"
                                        type="password"
                                        required
                                        autocomplete="off"
                                        placeholder="{{ __('Paste personal OAuth token') }}"
                                        class="profile-connect-token-input"
                                    >
                                    @error('playstrategy_token')
                                        <p class="profile-connect-token-error" role="alert">{{ $message }}</p>
                                    @enderror
                                    <div class="profile-connect-token-actions">
                                        <button type="submit" class="profile-connect-btn profile-connect-btn-primary">
                                            {{ __('Verify & Connect') }}
                                        </button>
                                        <button type="button" @click="openTokenForm = false" class="profile-connect-btn profile-connect-btn-ghost">
                                            {{ __('Cancel') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                </article>
            @endif

            @if($showGoogle)
                <article class="profile-connect-card {{ $googleAccount ? 'is-connected' : '' }}">
                    <div class="profile-connect-top">
                        <div class="profile-connect-brand">
                            <span class="profile-connect-icon profile-connect-icon-google" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="19" height="19">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="profile-connect-name">{{ __('Google Sign-In') }}</p>
                                <p class="profile-connect-meta">{{ __('Account Access') }}</p>
                            </div>
                        </div>

                        <span class="profile-connect-status {{ $googleAccount ? 'is-linked' : 'is-unlinked' }}">
                            <span class="profile-connect-dot" aria-hidden="true"></span>
                            {{ $googleAccount ? __('Connected') : __('Not linked') }}
                        </span>
                    </div>

                    <div class="profile-connect-body">
                        @if($googleAccount)
                            <p class="profile-connect-identity" translate="no">{{ $googleAccount->external_username }}</p>
                            <p class="profile-connect-desc">{{ __('Sign in with one click using your verified Google account.') }}</p>
                        @else
                            <p class="profile-connect-desc">{{ __('Link your Google account for faster, password-free sign-in across devices.') }}</p>
                        @endif
                    </div>

                    <div class="profile-connect-actions">
                        @if($googleAccount)
                            <form method="POST" action="{{ route('auth.google.disconnect') }}" class="profile-connect-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="profile-connect-btn profile-connect-btn-danger">
                                    {{ __('Disconnect Google') }}
                                </button>
                            </form>
                        @else
                            <a href="{{ route('auth.google.redirect') }}" class="profile-connect-btn profile-connect-btn-secondary">
                                <span>{{ __('Connect Google') }}</span>
                                <span aria-hidden="true">↗</span>
                            </a>
                        @endif
                    </div>
                </article>
            @endif
        </div>
    </section>
@endif
