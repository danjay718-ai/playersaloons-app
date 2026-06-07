<?php

namespace App\Shared\Enums;

enum KycStatus: string
{
    case NOT_SUBMITTED = 'not_submitted';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
