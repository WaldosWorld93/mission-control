<?php

namespace App\Enums;

enum MessageType: string
{
    case Chat = 'chat';
    case StatusUpdate = 'status_update';
    case TaskUpdate = 'task_update';
    case Standup = 'standup';
    case ReviewRequest = 'review_request';
}
