<?php

namespace App\Enums;

enum AgentStatus: string
{
    case Online = 'online';
    case Idle = 'idle';
    case Busy = 'busy';
    case Offline = 'offline';
    case Error = 'error';
}
