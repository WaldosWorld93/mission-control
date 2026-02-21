<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Blocked = 'blocked';
    case Backlog = 'backlog';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
