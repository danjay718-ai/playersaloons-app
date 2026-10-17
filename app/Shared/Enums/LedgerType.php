<?php

namespace App\Shared\Enums;

enum LedgerType: string
{
    case DEPOSIT = 'DEPOSIT';
    case WITHDRAWAL = 'WITHDRAWAL';
    case ENTRY_FEE = 'ENTRY_FEE';
    case H2H_STAKE = 'H2H_STAKE';
    case H2H_PAYOUT = 'H2H_PAYOUT';
    case REFUND = 'REFUND';
    case PRIZE = 'PRIZE';
    case PLATFORM_COMMISSION = 'PLATFORM_COMMISSION';
    case SPONSORED_PRIZE_RESERVE = 'SPONSORED_PRIZE_RESERVE';
    case SPONSORED_PRIZE_RELEASE = 'SPONSORED_PRIZE_RELEASE';
    case ADJUSTMENT = 'ADJUSTMENT';
    case REFERRAL_BONUS = 'REFERRAL_BONUS';

    public function label(): string
    {
        return __(match ($this) {
            self::DEPOSIT => 'Wallet top-ups',
            self::WITHDRAWAL => 'User withdrawals',
            self::ENTRY_FEE => 'Competition entry fees',
            self::H2H_STAKE => 'H2H entry amounts',
            self::H2H_PAYOUT => 'H2H rewards',
            self::REFUND => 'Money returned to users',
            self::PRIZE => 'Competition rewards',
            self::PLATFORM_COMMISSION => 'Platform service fees',
            self::SPONSORED_PRIZE_RESERVE => 'Prizes set aside',
            self::SPONSORED_PRIZE_RELEASE => 'Unused prize money returned',
            self::ADJUSTMENT => 'Balance corrections',
            self::REFERRAL_BONUS => 'Invite rewards',
        });
    }

    public function description(): string
    {
        return __(match ($this) {
            self::DEPOSIT => 'Money users add to their wallets. This belongs to users.',
            self::WITHDRAWAL => 'Money deducted from user wallets for withdrawals.',
            self::ENTRY_FEE => 'Money taken from user wallets to join competitions.',
            self::H2H_STAKE => 'Money set aside when players join a Head-to-Head challenge.',
            self::H2H_PAYOUT => 'Money awarded after a Head-to-Head challenge.',
            self::REFUND => 'Money returned to user wallets after a cancellation or refund.',
            self::PRIZE => 'Prizes added to player wallets after competition results.',
            self::PLATFORM_COMMISSION => 'Service fees collected by PlayerSaloons.',
            self::SPONSORED_PRIZE_RESERVE => 'Money set aside from the platform wallet for prizes.',
            self::SPONSORED_PRIZE_RELEASE => 'Unused prize money returned to the platform wallet. This is not new income.',
            self::ADJUSTMENT => 'Corrections made to wallet balances.',
            self::REFERRAL_BONUS => 'Rewards added to user wallets for inviting others.',
        });
    }
}
