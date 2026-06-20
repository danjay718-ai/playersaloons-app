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
    case ADJUSTMENT = 'ADJUSTMENT';
}
