<?php

namespace App\Shared\Enums;

enum LedgerType: string
{
    case DEPOSIT = 'DEPOSIT';
    case WITHDRAWAL = 'WITHDRAWAL';
    case ENTRY_FEE = 'ENTRY_FEE';
    case REFUND = 'REFUND';
    case PRIZE = 'PRIZE';
    case ADJUSTMENT = 'ADJUSTMENT';
}
