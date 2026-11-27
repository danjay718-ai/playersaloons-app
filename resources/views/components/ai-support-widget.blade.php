@props([
    'surface' => 'guest',
])

@php
    /** @var \App\Modules\Operations\Services\AiSupportAssistantService $aiSupport */
    $aiSupport = app(\App\Modules\Operations\Services\AiSupportAssistantService::class);
    $isAvailable = $aiSupport->isAvailable();
@endphp

@if($isAvailable)
@php
    $resolvedScope = $aiSupport->resolveScope(auth()->user());
    $starters = $aiSupport->starterPromptsForScope($resolvedScope);
    $isAdminTheme = $surface === 'admin';
    $isPlayerMobileShell = $surface === 'player';

    $scopeLabel = match ($resolvedScope) {
        'admin' => __('Admin'),
        'player' => __('Player'),
        default => __('Guest'),
    };
    $assistantTitle = $resolvedScope === 'admin'
        ? __('Admin Assistant')
        : __('GamersRival Help');
    $welcomeText = match ($resolvedScope) {
        'admin' => __('Ask about tournaments, AI dispute triage, deposits, notifications, or system settings.'),
        'player' => __('Ask about joining tournaments, PlayStrategy, wallet deposits/withdrawals, or match results.'),
        default => __('Ask how GamersRival works, how to register, or how to find open tournaments.'),
    };
@endphp
<div
    wire:ignore
    data-ai-support-root
    data-scope="{{ $resolvedScope }}"
    data-surface="{{ $surface }}"
    x-data="{
        open: false,
        loading: false,
        input: '',
        error: '',
        scope: @js($resolvedScope),
        storageKey: 'gr_ai_support_chat_v1:' + @js($resolvedScope),
        endpoint: @js(route('ai-support.ask')),
        fallbackToken: @js(csrf_token()),
        starters: @js($starters),
        messages: [],
        init() {
            try {
                const raw = window.sessionStorage.getItem(this.storageKey);
                const parsed = raw ? JSON.parse(raw) : [];
                if (Array.isArray(parsed)) {
                    this.messages = parsed.slice(-16);
                }
            } catch (e) {}
        },
        persist() {
            try {
                window.sessionStorage.setItem(this.storageKey, JSON.stringify(this.messages.slice(-16)));
            } catch (e) {}
        },
        scrollBottom() {
            this.$nextTick(() => {
                const el = this.$refs.messagesBox;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.scrollBottom();
                this.$nextTick(() => this.$refs.promptInput?.focus());
            }
        },
        clearChat() {
            this.messages = [];
            this.error = '';
            this.persist();
        },
        csrfToken() {
            return document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || this.fallbackToken;
        },
        async send(preset = null) {
            const text = (preset !== null ? preset : this.input).trim();
            if (text.length < 2 || this.loading) return;

            this.error = '';
            const history = this.messages.slice(-6).map((m) => ({ role: m.role, content: m.content }));
            this.messages.push({ id: Date.now() + '-u', role: 'user', content: text });
            if (preset === null) this.input = '';
            this.persist();
            this.loading = true;
            this.scrollBottom();

            try {
                const res = await fetch(this.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ message: text, history }),
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.error = data.message || (res.status === 429
                        ? @js(__('Too many questions in a short time. Please wait a moment.'))
                        : @js(__('Support assistant could not respond right now.')));
                    return;
                }

                if (data.reply) {
                    this.messages.push({ id: Date.now() + '-a', role: 'assistant', content: data.reply });
                    this.persist();
                    this.scrollBottom();
                }
            } catch (e) {
                this.error = @js(__('Network error. Check your connection and try again.'));
            } finally {
                this.loading = false;
                this.scrollBottom();
            }
        }
    }"
    @keydown.escape.window="if (open) open = false"
>
    {{-- Mobile-only subtle backdrop when open --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="open = false"
        class="fixed inset-0 z-[1050] bg-black/50 backdrop-blur-[1px] sm:hidden"
        aria-hidden="true"
    ></div>

    {{-- Compact Floating Chat Box --}}
    <section
        x-show="open"
        x-cloak
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-3 scale-95"
        role="dialog"
        aria-label="{{ $assistantTitle }}"
        data-ai-support-panel
        class="fixed inset-x-3 bottom-3 z-[1060] flex h-[min(480px,74dvh)] flex-col overflow-hidden rounded-2xl border {{ $isAdminTheme ? 'border-sky-400/25 bg-[#0f172a]' : 'border-cyan-400/25 bg-[#0b1322]' }} text-zinc-100 shadow-[0_20px_60px_rgba(0,0,0,0.75)] sm:inset-x-auto sm:bottom-20 sm:right-6 sm:w-[360px] sm:max-w-[calc(100vw-2rem)]"
    >
        {{-- Header --}}
        <header class="flex shrink-0 items-center justify-between gap-2 border-b border-white/10 {{ $isAdminTheme ? 'bg-[#0b1120]' : 'bg-[#080e1a]' }} px-4 py-3">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl {{ $isAdminTheme ? 'border border-sky-400/35 bg-sky-400/15 text-sky-300' : 'border border-[#c6ff3d]/35 bg-[#c6ff3d]/15 text-[#c6ff3d]' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" opacity="0" />
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                        <path d="M9.5 9.5h.01" />
                        <path d="M14.5 9.5h.01" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <h2 class="truncate text-xs font-bold tracking-wide text-white">{{ $assistantTitle }}</h2>
                        <span class="shrink-0 rounded-full border {{ $isAdminTheme ? 'border-sky-400/30 bg-sky-400/10 text-sky-300' : 'border-[#c6ff3d]/30 bg-[#c6ff3d]/10 text-[#c6ff3d]' }} px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider">{{ $scopeLabel }}</span>
                    </div>
                    <p class="truncate text-[10px] text-zinc-400">{{ __('Instant platform guide') }}</p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                <button
                    type="button"
                    x-show="messages.length > 0"
                    @click="clearChat()"
                    title="{{ __('Clear conversation') }}"
                    aria-label="{{ __('Clear conversation') }}"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-white/10 hover:text-white"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true">
                        <path d="M3 6h18" />
                        <path d="M8 6V4h8v2" />
                        <path d="M19 6l-1 14H6L5 6" />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="open = false"
                    aria-label="{{ __('Close support chat') }}"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[#c6ff3d]"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                    </svg>
                </button>
            </div>
        </header>

        {{-- Messages --}}
        <div x-ref="messagesBox" class="flex-1 space-y-3 overflow-y-auto overscroll-contain px-4 py-3.5 text-xs">
            {{-- Welcome & Starter Prompts --}}
            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-3 text-zinc-200">
                <p class="leading-relaxed">{{ $welcomeText }}</p>
                <div x-show="messages.length === 0" class="mt-2.5 flex flex-wrap gap-1.5">
                    <template x-for="starter in starters" :key="starter">
                        <button
                            type="button"
                            @click="send(starter)"
                            :disabled="loading"
                            class="rounded-lg border {{ $isAdminTheme ? 'border-sky-400/25 bg-sky-500/10 text-sky-200 hover:border-sky-400/50 hover:bg-sky-500/20' : 'border-cyan-400/25 bg-cyan-500/10 text-cyan-200 hover:border-[#c6ff3d]/50 hover:text-white' }} px-2.5 py-1.5 text-left text-[11px] font-medium transition disabled:opacity-50"
                            x-text="starter"
                        ></button>
                    </template>
                </div>
            </div>

            {{-- Conversation turns --}}
            <template x-for="msg in messages" :key="msg.id">
                <div class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div
                        class="max-w-[85%] whitespace-pre-line break-words rounded-2xl px-3.5 py-2.5 leading-relaxed"
                        :class="msg.role === 'user'
                            ? '{{ $isAdminTheme ? 'rounded-br-sm bg-sky-500 text-slate-950 font-semibold' : 'rounded-br-sm bg-[#c6ff3d] text-[#080f16] font-semibold' }}'
                            : 'rounded-bl-sm border border-white/10 bg-white/[0.06] text-zinc-100'"
                        x-text="msg.content"
                    ></div>
                </div>
            </template>

            {{-- Typing indicator --}}
            <div x-show="loading" x-cloak class="flex justify-start" role="status" aria-live="polite">
                <div class="inline-flex items-center gap-1.5 rounded-2xl rounded-bl-sm border border-white/10 bg-white/[0.06] px-3.5 py-2.5 text-zinc-400">
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-zinc-400"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-zinc-400 [animation-delay:120ms]"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-zinc-400 [animation-delay:240ms]"></span>
                </div>
            </div>

            {{-- Error message --}}
            <p x-show="error" x-cloak x-text="error" role="alert" class="rounded-lg border border-red-500/35 bg-red-950/50 px-3 py-2 text-[11px] text-red-200"></p>
        </div>

        {{-- Input Form --}}
        <form @submit.prevent="send()" class="flex shrink-0 items-center gap-2 border-t border-white/10 {{ $isAdminTheme ? 'bg-[#0b1120]' : 'bg-[#080e1a]' }} px-3 py-2.5 pb-[max(0.625rem,env(safe-area-inset-bottom))]">
            <input
                x-ref="promptInput"
                x-model="input"
                type="text"
                maxlength="1000"
                autocomplete="off"
                placeholder="{{ __('Ask a question…') }}"
                :disabled="loading"
                class="min-h-[40px] flex-1 rounded-xl border border-white/10 bg-white/[0.05] px-3 py-2 text-xs text-white placeholder-zinc-400 outline-none transition {{ $isAdminTheme ? 'focus:border-sky-400' : 'focus:border-[#c6ff3d]' }} disabled:opacity-60"
            >
            <button
                type="submit"
                :disabled="loading || input.trim().length < 2"
                aria-label="{{ __('Send message') }}"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $isAdminTheme ? 'bg-sky-400 text-slate-950 hover:bg-sky-300' : 'bg-[#c6ff3d] text-[#080f16] hover:brightness-105' }} transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                    <path d="m22 2-7 20-4-9-9-4Z" />
                    <path d="M22 2 11 13" />
                </svg>
            </button>
        </form>
    </section>

    {{-- Compact Floating Trigger Button --}}
    <button
        type="button"
        @click="toggle()"
        :aria-expanded="open"
        aria-label="{{ $assistantTitle }}"
        data-ai-support-trigger
        class="fixed {{ $isPlayerMobileShell ? 'bottom-[116px] right-4 lg:bottom-6 lg:right-6' : 'bottom-4 right-4 sm:bottom-6 sm:right-6' }} z-[900] inline-flex h-11 w-11 items-center justify-center rounded-full border {{ $isAdminTheme ? 'border-sky-400/50 bg-[#0f172a] text-sky-300 shadow-[0_10px_28px_rgba(14,165,233,0.28)] hover:border-sky-300 hover:bg-sky-400 hover:text-slate-950' : 'border-[#c6ff3d]/55 bg-[#0b1322] text-[#c6ff3d] shadow-[0_10px_28px_rgba(0,0,0,0.55)] hover:border-[#c6ff3d] hover:bg-[#c6ff3d] hover:text-[#080f16]' }} transition duration-150 hover:-translate-y-0.5 active:scale-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#c6ff3d] sm:h-12 sm:w-12"
    >
        <svg x-show="!open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            <path d="M9 10h6" />
            <path d="M9 14h3" />
        </svg>
        <svg x-show="open" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
            <path d="M18 6 6 18" />
            <path d="m6 6 12 12" />
        </svg>
    </button>
</div>
@endif
