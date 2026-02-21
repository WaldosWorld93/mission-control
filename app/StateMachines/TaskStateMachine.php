<?php

namespace App\StateMachines;

use App\Enums\TaskStatus;

class TaskStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'blocked' => ['backlog', 'cancelled'],
        'backlog' => ['assigned', 'cancelled'],
        'assigned' => ['in_progress', 'backlog', 'cancelled'],
        'in_progress' => ['in_review', 'done', 'assigned', 'cancelled'],
        'in_review' => ['done', 'in_progress', 'cancelled'],
        'done' => ['backlog'],
        'cancelled' => ['backlog'],
    ];

    /**
     * Statuses that only the system (not agents) can transition out of.
     *
     * @var list<string>
     */
    private const SYSTEM_ONLY_SOURCES = [
        'blocked',
    ];

    public static function canTransition(TaskStatus|string $from, TaskStatus|string $to): bool
    {
        $fromValue = $from instanceof TaskStatus ? $from->value : $from;
        $toValue = $to instanceof TaskStatus ? $to->value : $to;

        return in_array($toValue, self::ALLOWED_TRANSITIONS[$fromValue] ?? [], true);
    }

    public static function isSystemOnly(TaskStatus|string $status): bool
    {
        $value = $status instanceof TaskStatus ? $status->value : $status;

        return in_array($value, self::SYSTEM_ONLY_SOURCES, true);
    }

    /**
     * @return list<TaskStatus>
     */
    public static function allowedTransitions(TaskStatus|string $from): array
    {
        $fromValue = $from instanceof TaskStatus ? $from->value : $from;

        return array_map(
            fn (string $s) => TaskStatus::from($s),
            self::ALLOWED_TRANSITIONS[$fromValue] ?? []
        );
    }
}
