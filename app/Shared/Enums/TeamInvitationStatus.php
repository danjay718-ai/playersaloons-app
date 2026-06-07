<?php

namespace App\Shared\Enums;

enum TeamInvitationStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
}
