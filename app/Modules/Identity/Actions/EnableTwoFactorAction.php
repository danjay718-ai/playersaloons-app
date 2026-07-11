<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TotpService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnableTwoFactorAction
{
    /** @return list<string> */
    public function execute(User $user, string $secret, string $code): array
    {
        if (! app(TotpService::class)->verify($secret, $code)) {
            throw ValidationException::withMessages(['twoFactorCode' => 'The authentication code is invalid.']);
        }

        $recoveryCodes = collect(range(1, 8))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->values()
            ->all();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn (string $value) => Hash::make($value), $recoveryCodes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        activity()->causedBy($user)->performedOn($user)->log('two_factor_enabled');

        return $recoveryCodes;
    }
}
