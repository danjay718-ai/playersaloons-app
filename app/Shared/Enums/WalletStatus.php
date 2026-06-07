<?php

namespace App\Shared\Enums;

enum WalletStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case FROZEN = 'frozen';
}
