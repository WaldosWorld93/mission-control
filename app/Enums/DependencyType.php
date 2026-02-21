<?php

namespace App\Enums;

enum DependencyType: string
{
    case FinishToStart = 'finish_to_start';
    case FinishToReview = 'finish_to_review';
}
