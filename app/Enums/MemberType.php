<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enum;

enum MemberType: int
{
    use Enum;

    case MEMBER = 117100;
    case SFAFF = 117200;
    case MASTER_STAFF = 117300;
}

