<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Failed = 'failed';
    case Reassigned = 'reassigned';
    case TimedOut = 'timed_out';
}
