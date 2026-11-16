<?php

namespace App\Enums;

enum UserRole: string
{
    case Candidate = 'candidate';
    case Recruiter = 'recruiter';
    case Admin = 'admin';
}
