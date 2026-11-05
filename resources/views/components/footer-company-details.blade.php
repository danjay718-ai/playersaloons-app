@php
    $companyDetails = \App\Modules\Operations\Models\SystemSetting::query()->where('key', 'footer.company_details')->value('value');
@endphp
@if(filled($companyDetails))
    <div class="border-t border-white/10 py-6 text-left">
        <h2 class="mb-3 text-sm font-semibold text-zinc-300">{{ __('Company Details') }}</h2>
        <p class="max-w-lg whitespace-pre-line break-words text-sm leading-7 text-zinc-400">{{ $companyDetails }}</p>
    </div>
@endif
