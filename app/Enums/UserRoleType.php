<?php

namespace App\Enums;

enum UserRoleType: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case User = 'user';
}
