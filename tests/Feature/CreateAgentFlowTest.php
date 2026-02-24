<?php

use App\Filament\Resources\AgentResource\Pages\CreateAgent;
use App\Models\Agent;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->team = Team::factory()->create(['onboarding_completed_at' => now()]);
    $this->user = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->users()->attach($this->user, ['role' => 'owner']);
    app(\App\Services\TeamContext::class)->set($this->team);
});

it('redirects to setup page after creating an agent', function () {
    $this->actingAs($this->user);

    $response = Livewire::test(CreateAgent::class)
        ->fillForm([
            'name' => 'Test Agent',
            'role' => 'Tester',
            'description' => 'Runs tests.',
            'heartbeat_model' => 'claude-haiku-4-20250414',
            'work_model' => 'claude-sonnet-4-20250514',
        ])
        ->call('create');

    $agent = Agent::where('name', 'Test Agent')->first();
    expect($agent)->not->toBeNull();

    $response->assertRedirect(url("agents/{$agent->id}/setup"));
});

it('auto-generates soul_md when left blank on create', function () {
    $this->actingAs($this->user);

    Livewire::test(CreateAgent::class)
        ->fillForm([
            'name' => 'Scout',
            'role' => 'Researcher',
            'description' => 'Finds answers to complex questions.',
        ])
        ->call('create');

    $agent = Agent::where('name', 'Scout')->first();

    expect($agent->soul_md)
        ->not->toBeNull()
        ->toContain('# Scout')
        ->toContain('Researcher')
        ->toContain('Finds answers to complex questions.')
        ->toContain('## Working Style');
});

it('preserves user-provided soul_md on create', function () {
    $this->actingAs($this->user);

    $customSoul = "# My Custom Agent\n\nDo things my way.";

    Livewire::test(CreateAgent::class)
        ->fillForm([
            'name' => 'Custom Bot',
            'role' => 'Worker',
            'soul_md' => $customSoul,
        ])
        ->call('create');

    $agent = Agent::where('name', 'Custom Bot')->first();

    expect($agent->soul_md)->toBe($customSoul);
});

it('stores token in session for setup page after create', function () {
    $this->actingAs($this->user);

    Livewire::test(CreateAgent::class)
        ->fillForm([
            'name' => 'Token Agent',
            'role' => 'Worker',
        ])
        ->call('create');

    $deployedTokens = session('deployed_tokens');
    expect($deployedTokens)->toBeArray()
        ->and($deployedTokens)->toHaveCount(1)
        ->and($deployedTokens[0]['name'])->toBe('Token Agent')
        ->and($deployedTokens[0]['token'])->toHaveLength(64);
});

it('shows success banner on setup page after agent creation', function () {
    $this->actingAs($this->user);

    $agent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Banner Agent',
    ]);

    $this->actingAs($this->user)
        ->withSession(['agent_created' => true])
        ->get("/agents/{$agent->id}/setup")
        ->assertSeeText('Agent created! Follow the steps below to connect it.');
});

it('does not show success banner on setup page without flash', function () {
    $this->actingAs($this->user);

    $agent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'No Banner Agent',
    ]);

    $this->actingAs($this->user)
        ->get("/agents/{$agent->id}/setup")
        ->assertDontSeeText('Agent created! Follow the steps below to connect it.');
});

it('shows edit link on setup page step 7 when soul_md exists', function () {
    $this->actingAs($this->user);

    $agent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Soul Agent',
        'soul_md' => '# Soul Agent Identity',
    ]);

    $this->actingAs($this->user)
        ->get("/agents/{$agent->id}/setup")
        ->assertSeeText('Edit')
        ->assertSee('# Soul Agent Identity');
});
