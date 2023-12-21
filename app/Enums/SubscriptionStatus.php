<?php
declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enum;

enum SubscriptionStatus
{
    use Enum;

    case NOT_USE;
    case USE;
    case USED;
}