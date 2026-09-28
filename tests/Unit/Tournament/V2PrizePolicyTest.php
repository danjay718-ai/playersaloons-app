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
            'underfilled four' => [4, 3, '1.00', '0.30', '2.70', '0.00'],
            'two entrants in a larger bracket' => [16, 2, '1.00', '0.20', '1.80', '0.00'],
            'four entrants in a larger bracket' => [16, 4, '1.00', '0.40', '3.60', '0.00'],
            'five entrants use configured split' => [16, 5, '1.00', '0.50', '3.75', '0.75'],
            'underfilled sixteen' => [16, 10, '1.00', '1.00', '7.50', '1.50'],
            'cent rounding remains conserved' => [8, 8, '0.01', '0.01', '0.06', '0.01'],
        ];
    }

    public function test_sponsored_policy_uses_fixed_prizes_without_commission(): void
    {
        $tournament = new Tournament([
            'max_participants' => 16,
            'entry_fee' => '0.00',
            'prize_funding_mode' => 'sponsored',
            'prize_1st' => '12.34',
            'prize_2nd' => '5.67',
        ]);

        $result = (new V2PrizePolicy)->calculate($tournament, 6);

        self::assertSame('18.01', $result['gross']);
        self::assertSame('0.00', $result['commission']);
        self::assertSame('12.34', $result['first']);
        self::assertSame('5.67', $result['second']);
    }
}
