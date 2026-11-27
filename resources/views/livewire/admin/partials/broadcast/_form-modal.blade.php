{{--
  Partial: broadcast/_form-modal.blade.php
  Instant zero-lag Alpine.js modal; submits to Livewire only on Save.
--}}
<div
    x-show="formOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
>
    <div class="fixed inset-0" @click="closeFormModal()" aria-hidden="true"></div>
    <div class="relative z-10 bg-[#0f172a] border border-slate-700 rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] flex flex-col overflow-hidden"
         x-trap.noscroll="formOpen">

        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800 shrink-0">
            <h3 class="text-sm font-bold uppercase tracking-widest text-slate-200"
                x-text="editingId ? @js(__('Edit Broadcast / Announcement')) : @js(__('New Broadcast / Announcement'))">
            </h3>
            <button type="button" @click="closeFormModal()"
                    class="p-1.5 text-slate-500 hover:text-white transition-colors rounded-lg hover:bg-slate-800">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4 overflow-y-auto flex-1">
            {{-- Display mode & Target --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">{{ __('Display Mode') }}</label>
                    <select x-model="displayType"
                            @change="$wire.$set('displayType', displayType, false)"
                            class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3.5 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 transition-colors">
                        <option value="feed">{{ __('Dashboard Feed Only') }}</option>
                        <option value="popup_modal">{{ __('What\'s New Popup Modal') }}</option>
                        <option value="both">{{ __('Both Feed & Popup Modal') }}</option>
                    </select>
                    @error('displayType') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div x-show="displayType !== 'feed'" x-cloak>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">{{ __('Show Popup On') }}</label>
                    <select x-model="targetSurface"
                            @change="$wire.$set('targetSurface', targetSurface, false)"
                            class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3.5 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 transition-colors">
                        <option value="both">{{ __('Homepage & Player Dashboard') }}</option>
                        <option value="homepage">{{ __('Homepage Only') }}</option>
                        <option value="player_dashboard">{{ __('Player Dashboard Only') }}</option>
                    </select>
                    @error('targetSurface') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Badge Label (Popup only) --}}
            <div x-show="displayType !== 'feed'" x-cloak>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">{{ __('Badge Label') }} <span class="text-slate-500 normal-case">({{ __('optional, defaults to "What\'s New"') }})</span></label>
                <input wire:model="badgeLabel" type="text" maxlength="60"
                       class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors"
                       placeholder="e.g. What's New, New Partnership, Update Is Here!">
                @error('badgeLabel') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- Title --}}
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">{{ __('Title') }}</label>
                <input wire:model="title" type="text" maxlength="255"
                       class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors"
                       placeholder="e.g. PlayStrategy Partnership Is Live!">
                @error('title') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- Media options (Popup only) --}}
            <div x-show="displayType !== 'feed'" x-cloak class="rounded-xl border border-slate-800 bg-slate-900/50 p-4 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">{{ __('Media Type') }}</label>
                        <select x-model="mediaType"
                                @change="if (mediaType === 'none') { isVideoOnly = false; $wire.$set('isVideoOnly', false, false); } $wire.$set('mediaType', mediaType, false)"
                                class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3.5 py-2 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                            <option value="none">{{ __('Text Only') }}</option>
                            <option value="image">{{ __('Image Banner') }}</option>
                            <option value="video">{{ __('Video (YouTube / Vimeo / MP4)') }}</option>
                        </select>
                        @error('mediaType') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <label x-show="mediaType !== 'none'" x-cloak class="mt-2 sm:mt-5 inline-flex items-center gap-2.5 text-xs text-slate-200 cursor-pointer select-none">
                        <input type="checkbox" x-model="isVideoOnly"
                               @change="$wire.$set('isVideoOnly', isVideoOnly, false)"
                               class="h-4 w-4 rounded border-slate-600 bg-slate-950 text-indigo-500 focus:ring-indigo-500">
                        <span>{{ __('Full media-only popup (body text optional)') }}</span>
                    </label>
                </div>

                <div x-show="mediaType !== 'none'" x-cloak class="space-y-3">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5"
                               x-text="mediaType === 'video' ? @js(__('Video URL (YouTube, Vimeo, or direct MP4/WebM)')) : @js(__('Image URL'))">
                        </label>
                        <input wire:model="mediaUrl" type="text" maxlength="1000"
                               class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3.5 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                               :placeholder="mediaType === 'video' ? 'https://www.youtube.com/watch?v=... or https://.../promo.mp4' : 'https://.../announcement-banner.webp'">
                        @error('mediaUrl') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5"
                               x-text="mediaType === 'video' ? @js(__('Or Upload MP4 / WebM (max 20MB)')) : @js(__('Or Upload Image (max 4MB)'))">
                        </label>
                        <input wire:model="mediaFile" type="file"
                               :accept="mediaType === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp,image/gif'"
                               class="block w-full text-xs text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-600/80 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-indigo-500">
                        <p wire:loading wire:target="mediaFile" class="mt-1 text-[11px] text-indigo-300">{{ __('Uploading media…') }}</p>
                        @error('mediaFile') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Message --}}
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">
                    {{ __('Message') }}
                    <span x-show="displayType !== 'feed' && mediaType !== 'none' && isVideoOnly" x-cloak class="text-slate-500 normal-case">({{ __('optional for full media-only popup') }})</span>
                </label>
                <textarea wire:model="message" rows="3" maxlength="2000"
                          class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-colors resize-none"
                          placeholder="{{ __('Share update highlights, partnership details, or news...') }}"></textarea>
                @error('message') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- CTA Button (Popup only) --}}
            <div x-show="displayType !== 'feed'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">{{ __('CTA Button Label') }} <span class="text-slate-600 normal-case">({{ __('optional') }})</span></label>
                    <input wire:model="ctaLabel" type="text" maxlength="80"
                           class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                           placeholder="e.g. Explore Tournaments">
                    @error('ctaLabel') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">{{ __('CTA Link URL') }} <span class="text-slate-600 normal-case">({{ __('optional') }})</span></label>
                    <input wire:model="ctaUrl" type="text" maxlength="1000"
                           class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                           placeholder="/tournaments/browse or https://...">
                    @error('ctaUrl') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Dates --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">Starts At <span class="text-slate-600 normal-case">(optional)</span></label>
                    <input wire:model="startsAt" type="datetime-local"
                           class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 transition-colors">
                    @error('startsAt') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">Ends At <span class="text-slate-600 normal-case">(optional)</span></label>
                    <input wire:model="endsAt" type="datetime-local"
                           class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 transition-colors">
                    @error('endsAt') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-slate-800 flex justify-end gap-3 shrink-0">
            <button type="button" @click="closeFormModal()"
                    class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-400 hover:text-white border border-slate-700 hover:border-slate-600 rounded-lg transition-colors">
                Cancel
            </button>
            <button type="button" wire:click="save" wire:loading.attr="disabled"
                    class="px-4 py-2 text-xs font-bold uppercase tracking-wider bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors disabled:opacity-50">
                <span wire:loading.remove wire:target="save" x-text="editingId ? 'Update' : 'Create'"></span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </div>
</div>
