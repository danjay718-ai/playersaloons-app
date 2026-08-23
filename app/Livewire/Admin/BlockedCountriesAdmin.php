<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Compliance\Models\BlockedCountry;
use App\Modules\Compliance\Services\CountryEligibilityService;
use Illuminate\Support\Facades\Auth;

class BlockedCountriesAdmin extends AdminComponent
{
    public string $countryCode = '';

    public string $message = 'Our services are currently not available in your region due to regulatory restrictions.';

    public function boot(): void
    {
        parent::boot();
        if (! $this->actor()->hasAnyRole(['SUPER_ADMIN', 'ADMIN'])) {
            abort(403);
        }
    }

    public function addCountry(): void
    {
        $this->validate([
            'countryCode' => ['required', 'string', 'size:2', 'in:'.implode(',', array_keys(config('countries', [])))],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $countryName = config("countries.{$this->countryCode}", $this->countryCode);

        BlockedCountry::updateOrCreate(
            ['country_code' => strtoupper($this->countryCode)],
            [
                'country_name' => $countryName,
                'message' => $this->message,
                'updated_by' => Auth::id(),
            ]
        );

        app(CountryEligibilityService::class)->forget();

        $this->reset(['countryCode']);
        $this->message = 'Our services are currently not available in your region due to regulatory restrictions.';
        session()->flash('success', 'Blocked country added/updated successfully.');
    }

    public function removeCountry(string $code): void
    {
        BlockedCountry::where('country_code', $code)->delete();
        app(CountryEligibilityService::class)->forget();
        session()->flash('success', "Unblocked $code successfully.");
    }

    public function render()
    {
        $blocked = BlockedCountry::with('updatedBy')->orderBy('country_name')->get();

        return view('livewire.admin.blocked-countries-admin', [
            'blockedCountries' => $blocked,
        ])->layout('components.layouts.admin', ['admin_title' => 'Geo-Blocking']);
    }
}
