<?php

declare(strict_types=1);

namespace Tests\Unit\Tournament;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\V2PrizePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class V2PrizePolicyTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_policy_conserves_every_cent(int $maximum, int $joined, string $fee, string $commission, string $first, string $second): void
    {
        $tournament = new Tournament([
            'max_participants' => $maximum,
            'entry_fee' => $fee,
            'full_first_bps' => 7500,
            'full_second_bps' => 1500,
            'full_platform_bps' => 1000,
            'underfilled_first_bps' => 8500,
            'underfilled_platform_bps' => 1500,
        ]);

        $result = (new V2PrizePolicy)->calculate($tournament, $joined);

        self::assertSame($commission, $result['commission']);
        self::assertSame($first, $result['first']);
        self::assertSame($second, $result['second']);
        self::assertSame($result['gross_minor'], $result['commission_minor'] + $result['first_minor'] + $result['second_minor']);
    }

    public static function cases(): array
    {
        return [
            'full four' => [4, 4, '1.00', '0.40', '3.60', '0.00'],
            'full eight configured split' => [8, 8, '1.00', '0.80', '6.00', '1.20'],
            'underfilled four' => [4, 3, '1.00', '0.45', '2.55', '0.00'],
            'underfilled sixteen' => [16, 10, '1.00', '1.50', '8.50', '0.00'],
            'cent rounding remains conserved' => [8, 8, '0.01', '0.01', '0.06', '0.01'],
        ];
    }
}
