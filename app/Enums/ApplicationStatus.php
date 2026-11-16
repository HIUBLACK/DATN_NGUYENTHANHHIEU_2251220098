<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Applied = 'applied';
    case Reviewing = 'reviewing';
    case Interview = 'interview';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}
