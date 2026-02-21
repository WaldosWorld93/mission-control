<?php

use App\Enums\TaskStatus;
use App\StateMachines\TaskStateMachine;

// Valid transitions
it('allows blocked to backlog', function () {
    expect(TaskStateMachine::canTransition('blocked', 'backlog'))->toBeTrue();
});

it('allows blocked to cancelled', function () {
    expect(TaskStateMachine::canTransition('blocked', 'cancelled'))->toBeTrue();
});

it('allows backlog to assigned', function () {
    expect(TaskStateMachine::canTransition('backlog', 'assigned'))->toBeTrue();
});

it('allows backlog to cancelled', function () {
    expect(TaskStateMachine::canTransition('backlog', 'cancelled'))->toBeTrue();
});

it('allows assigned to in_progress', function () {
    expect(TaskStateMachine::canTransition('assigned', 'in_progress'))->toBeTrue();
});

it('allows assigned to backlog', function () {
    expect(TaskStateMachine::canTransition('assigned', 'backlog'))->toBeTrue();
});

it('allows assigned to cancelled', function () {
    expect(TaskStateMachine::canTransition('assigned', 'cancelled'))->toBeTrue();
});

it('allows in_progress to in_review', function () {
    expect(TaskStateMachine::canTransition('in_progress', 'in_review'))->toBeTrue();
});

it('allows in_progress to done', function () {
    expect(TaskStateMachine::canTransition('in_progress', 'done'))->toBeTrue();
});

it('allows in_progress to assigned (reassignment)', function () {
    expect(TaskStateMachine::canTransition('in_progress', 'assigned'))->toBeTrue();
});

it('allows in_progress to cancelled', function () {
    expect(TaskStateMachine::canTransition('in_progress', 'cancelled'))->toBeTrue();
});

it('allows in_review to done', function () {
    expect(TaskStateMachine::canTransition('in_review', 'done'))->toBeTrue();
});

it('allows in_review to in_progress (rework)', function () {
    expect(TaskStateMachine::canTransition('in_review', 'in_progress'))->toBeTrue();
});

it('allows in_review to cancelled', function () {
    expect(TaskStateMachine::canTransition('in_review', 'cancelled'))->toBeTrue();
});

it('allows done to backlog (reopen)', function () {
    expect(TaskStateMachine::canTransition('done', 'backlog'))->toBeTrue();
});

it('allows cancelled to backlog (reopen)', function () {
    expect(TaskStateMachine::canTransition('cancelled', 'backlog'))->toBeTrue();
});

// Invalid transitions
it('rejects blocked to in_progress', function () {
    expect(TaskStateMachine::canTransition('blocked', 'in_progress'))->toBeFalse();
});

it('rejects blocked to assigned', function () {
    expect(TaskStateMachine::canTransition('blocked', 'assigned'))->toBeFalse();
});

it('rejects blocked to done', function () {
    expect(TaskStateMachine::canTransition('blocked', 'done'))->toBeFalse();
});

it('rejects backlog to in_progress (must be assigned first)', function () {
    expect(TaskStateMachine::canTransition('backlog', 'in_progress'))->toBeFalse();
});

it('rejects backlog to done', function () {
    expect(TaskStateMachine::canTransition('backlog', 'done'))->toBeFalse();
});

it('rejects done to in_progress', function () {
    expect(TaskStateMachine::canTransition('done', 'in_progress'))->toBeFalse();
});

it('rejects cancelled to done', function () {
    expect(TaskStateMachine::canTransition('cancelled', 'done'))->toBeFalse();
});

it('rejects same-status transitions', function () {
    expect(TaskStateMachine::canTransition('backlog', 'backlog'))->toBeFalse();
    expect(TaskStateMachine::canTransition('done', 'done'))->toBeFalse();
});

// System-only checks
it('identifies blocked as system-only status', function () {
    expect(TaskStateMachine::isSystemOnly('blocked'))->toBeTrue();
    expect(TaskStateMachine::isSystemOnly(TaskStatus::Blocked))->toBeTrue();
});

it('identifies non-system statuses', function () {
    expect(TaskStateMachine::isSystemOnly('backlog'))->toBeFalse();
    expect(TaskStateMachine::isSystemOnly('assigned'))->toBeFalse();
    expect(TaskStateMachine::isSystemOnly(TaskStatus::InProgress))->toBeFalse();
});

// Enum support
it('works with TaskStatus enums', function () {
    expect(TaskStateMachine::canTransition(TaskStatus::Backlog, TaskStatus::Assigned))->toBeTrue();
    expect(TaskStateMachine::canTransition(TaskStatus::Blocked, TaskStatus::InProgress))->toBeFalse();
});

// allowedTransitions
it('returns allowed transitions as enum array', function () {
    $transitions = TaskStateMachine::allowedTransitions(TaskStatus::InProgress);

    expect($transitions)->toContain(TaskStatus::InReview)
        ->toContain(TaskStatus::Done)
        ->toContain(TaskStatus::Assigned)
        ->toContain(TaskStatus::Cancelled)
        ->not->toContain(TaskStatus::Backlog)
        ->not->toContain(TaskStatus::Blocked);
});
