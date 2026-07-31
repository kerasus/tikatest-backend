<?php

namespace App\Enums;

enum UserRoleType: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Teacher = 'teacher';
    case Student = 'student';
    case Staff = 'staff';
    case Guardian = 'guardian';
}
