<?php

use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->team = Team::factory()->create(['onboarding_completed_at' => null]);
    $this->user = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->users()->attach($this->user, ['role' => 'owner']);
    app(\App\Services\TeamContext::class)->set($this->team);
});

it('redirects to onboarding when team has not completed onboarding', function () {
    $this->actingAs($this->user)
        ->get('/home')
        ->assertRedirect('/onboarding');
});

it('shows onboarding page for new teams', function () {
    $this->actingAs($this->user)
        ->get('/onboarding')
        ->assertSuccessful()
        ->assertSeeText('Welcome to Mission Control');
});

it('allows access to template pages during onboarding', function () {
    $this->actingAs($this->user)
        ->get('/templates')
        ->assertSuccessful();
});

it('does not redirect when onboarding is complete', function () {
    $this->team->update(['onboarding_completed_at' => now()]);

    $response = $this->actingAs($this->user)
        ->get('/home');

    // Should NOT redirect to onboarding — any non-redirect response is valid
    expect($response->isRedirect(url('/onboarding')))->toBeFalse();
});

it('can skip onboarding', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\OnboardingWizard::class)
        ->call('skip')
        ->assertRedirect('/home');

    $this->team->refresh();

    expect($this->team->onboarding_completed_at)->not->toBeNull();
});

it('completes onboarding when choosing template path', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\OnboardingWizard::class)
        ->call('chooseTemplate')
        ->assertRedirect('/templates');

    $this->team->refresh();

    expect($this->team->onboarding_completed_at)->not->toBeNull();
});

it('completes onboarding when choosing manual path', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\OnboardingWizard::class)
        ->call('chooseManual');

    $this->team->refresh();

    expect($this->team->onboarding_completed_at)->not->toBeNull();
});

it('shows existing agents step', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\OnboardingWizard::class)
        ->call('chooseExisting')
        ->assertSet('step', 'existing')
        ->assertSeeText('Connect Existing Agents');
});

it('completes onboarding from existing agents flow', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\OnboardingWizard::class)
        ->call('chooseExisting')
        ->call('finishExisting');

    $this->team->refresh();

    expect($this->team->onboarding_completed_at)->not->toBeNull();
});
