<?php
declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enum;

enum McpStatus: int
{
    use Enum;

    case UNUSE =  122100;
    case ORDER_USE =  122150;
    case USE =  122200;
    case WITHDRAW_ADMIN =  122300;
    case WITHDRAW_EXPIRATION =  122400;
    case USER_DELETE =  122500;
    case COMPANY_REFUND =  122900;
}