<?php

namespace App\Enums;

enum CompanyStatus: string
{
    case Active = 'active';
    case Locked = 'locked';
}
