<?php

use App\Models\Agent;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->team = Team::factory()->create(['onboarding_completed_at' => now()]);
    $this->user = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->users()->attach($this->user, ['role' => 'owner']);
    app(\App\Services\TeamContext::class)->set($this->team);

    $this->agent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Scout',
        'role' => 'Researcher',
    ]);
});

it('renders the setup page for an agent', function () {
    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSuccessful()
        ->assertSeeText('Setup: Scout');
});

it('shows prerequisites section', function () {
    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSeeText('Prerequisites')
        ->assertSeeText('OpenClaw');
});

it('shows add agent to openclaw gateway section', function () {
    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSeeText('Add Agent to OpenClaw Gateway')
        ->assertSeeText('openclaw.json');
});

it('shows configure agent workspace files section', function () {
    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSeeText('Configure Agent Workspace Files');
});

it('shows environment variables section with api url', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent])
        ->assertSeeText('Environment Variables')
        ->assertSee('MC_API_URL');
});

it('shows cron configuration section', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent])
        ->assertSee('Configure Heartbeat Cron')
        ->assertSee('Mission Control Heartbeat');
});

it('shows test connection section with curl command', function () {
    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSeeText('Test the Connection')
        ->assertSeeText('heartbeat');
});

it('uses default 3-minute interval for standard agents', function () {
    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSee('*/3 * * * *');
});

it('uses 2-minute interval for lead agents', function () {
    $this->agent->update(['is_lead' => true]);

    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSee('*/2 * * * *');
});

it('uses 10-minute interval for monitor role agents', function () {
    $this->agent->update(['role' => 'Monitor Bot']);

    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSee('*/10 * * * *');
});

it('displays token from session when available', function () {
    $plainToken = 'test-token-abc123';
    session(['deployed_tokens' => [['name' => 'Scout', 'token' => $plainToken]]]);

    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSee($plainToken);
});

it('can regenerate the agent token', function () {
    $this->actingAs($this->user);

    $oldTokenHash = $this->agent->api_token;

    Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent])
        ->call('regenerateToken')
        ->assertSet('plainToken', fn ($value) => $value !== null && strlen($value) === 64)
        ->assertSet('tokenIsNew', true)
        ->assertSeeHtml('Save this token now');

    $this->agent->refresh();
    expect($this->agent->api_token)->not->toBe($oldTokenHash);
});

it('shows both env vars in one block when token is available', function () {
    $plainToken = 'test-token-abc123';
    session(["agent_token_{$this->agent->id}" => $plainToken]);

    $this->actingAs($this->user);

    Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent])
        ->assertSee('MC_API_URL')
        ->assertSee('MC_AGENT_TOKEN')
        ->assertSee($plainToken);
});

it('shows skill installation section', function () {
    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSeeText('Install Mission Control Skills')
        ->assertSeeText('mission-control-heartbeat');
});

it('can switch skill tabs', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent])
        ->assertSet('skillTab', 'ask')
        ->call('setSkillTab', 'manual')
        ->assertSet('skillTab', 'manual');
});

it('shows soul md section when agent has soul content', function () {
    $this->agent->update(['soul_md' => '# Scout SOUL']);

    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSeeText('SOUL.md');
});

it('uses custom heartbeat model when set on agent', function () {
    $this->agent->update(['heartbeat_model' => 'openai/gpt-4o-mini']);

    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSee('openai/gpt-4o-mini');
});

it('defaults to claude haiku for heartbeat model', function () {
    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSee('anthropic/claude-haiku-4-5');
});

it('is accessible during onboarding', function () {
    $this->team->update(['onboarding_completed_at' => null]);

    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSuccessful();
});

it('skill content avoids heartbeat keyword in agent-facing instructions', function () {
    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    // The heartbeat skill should be titled "Sync with Mission Control", not contain "heartbeat" in agent-facing title
    $viewData = $page->viewData('heartbeatSkillMd');
    expect($viewData)->toContain('Sync with Mission Control');
    // The first heading should NOT say "heartbeat"
    $firstLine = explode("\n", $viewData)[0];
    expect(strtolower($firstLine))->not->toContain('heartbeat');
});

it('skill content contains correct API endpoint paths', function () {
    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    $heartbeatSkill = $page->viewData('heartbeatSkillMd');
    expect($heartbeatSkill)->toContain('$MC_API_URL/heartbeat');
    expect($heartbeatSkill)->toContain('$MC_AGENT_TOKEN');
    expect($heartbeatSkill)->toContain('soul_hash');
    expect($heartbeatSkill)->toContain('soul_sync');

    $tasksSkill = $page->viewData('tasksSkillMd');
    expect($tasksSkill)->toContain('$MC_API_URL/tasks');
    expect($tasksSkill)->toContain('$MC_API_URL/messages');
    expect($tasksSkill)->toContain('$MC_API_URL/tasks/$TASK_ID/claim');
    expect($tasksSkill)->toContain('$MC_API_URL/tasks/$TASK_ID/artifacts');
    expect($tasksSkill)->toContain('$MC_API_URL/projects');
    expect($tasksSkill)->toContain('$MC_API_URL/soul');
});

it('skill content references env vars correctly', function () {
    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    $heartbeatSkill = $page->viewData('heartbeatSkillMd');
    $tasksSkill = $page->viewData('tasksSkillMd');

    // Both skills should reference env vars, not hardcoded URLs
    expect($heartbeatSkill)->toContain('$MC_API_URL');
    expect($heartbeatSkill)->toContain('$MC_AGENT_TOKEN');
    expect($tasksSkill)->toContain('$MC_API_URL');
    expect($tasksSkill)->toContain('$MC_AGENT_TOKEN');

    // Neither should contain hardcoded localhost URLs
    expect($heartbeatSkill)->not->toContain('http://localhost');
    expect($tasksSkill)->not->toContain('http://localhost');
});

it('skill content includes error handling guidance', function () {
    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    $heartbeatSkill = $page->viewData('heartbeatSkillMd');
    expect($heartbeatSkill)->toContain('401');
    expect($heartbeatSkill)->toContain('422');
    expect($heartbeatSkill)->toContain('429');
    expect($heartbeatSkill)->toContain('500');

    $tasksSkill = $page->viewData('tasksSkillMd');
    expect($tasksSkill)->toContain('409');
    expect($tasksSkill)->toContain('401');
    expect($tasksSkill)->toContain('422');
});

it('skill content includes example responses', function () {
    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    $heartbeatSkill = $page->viewData('heartbeatSkillMd');
    // Should have example JSON in the response
    expect($heartbeatSkill)->toContain('"status": "ok"');
    expect($heartbeatSkill)->toContain('"notifications"');
    expect($heartbeatSkill)->toContain('"tasks"');

    $tasksSkill = $page->viewData('tasksSkillMd');
    expect($tasksSkill)->toContain('"data"');
    expect($tasksSkill)->toContain('"thread_id"');
});

it('tasks skill includes 409 conflict handling for claim', function () {
    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    $tasksSkill = $page->viewData('tasksSkillMd');
    expect($tasksSkill)->toContain('409');
    expect($tasksSkill)->toContain('already claimed');
    expect($tasksSkill)->toContain('Do not retry');
});

it('cron config in context has name and crons keys', function () {
    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    $cronConfig = $page->viewData('cronConfigInContext');
    $decoded = json_decode($cronConfig, true);

    expect($decoded)->toHaveKey('name');
    expect($decoded)->toHaveKey('crons');
    expect($decoded['crons'])->toBeArray();
    expect($decoded['crons'][0])->toHaveKey('name', 'Mission Control Heartbeat');
    expect($decoded['crons'][0]['payload']['message'])->toContain('Sync with Mission Control');
});

it('generates correct slug for agent', function () {
    expect($this->agent->slug)->toBe('scout');

    $agent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Content Lead',
    ]);
    expect($agent->slug)->toBe('content-lead');
});

it('generates correct workspace path for lead vs non-lead agents', function () {
    expect($this->agent->workspace_path)->toBe('~/.openclaw/workspace-scout');

    $this->agent->update(['is_lead' => true]);
    $this->agent->refresh();
    expect($this->agent->workspace_path)->toBe('~/.openclaw/workspace');
});

it('renders squad progress bar on setup page', function () {
    $this->actingAs($this->user)
        ->get("/agents/{$this->agent->id}/setup")
        ->assertSeeLivewire('squad-progress-bar');
});

it('can toggle workspace file collapsibles', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent])
        ->assertSet('expandedFile', null)
        ->call('toggleFile', 'identity')
        ->assertSet('expandedFile', 'identity')
        ->call('toggleFile', 'identity')
        ->assertSet('expandedFile', null)
        ->call('toggleFile', 'agents')
        ->assertSet('expandedFile', 'agents');
});

it('openclaw agent config contains expected keys', function () {
    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    $config = $page->viewData('openclawAgentConfig');
    $decoded = json_decode($config, true);

    expect($decoded)->toHaveKey('name', 'scout');
    expect($decoded)->toHaveKey('workspace');
    expect($decoded)->toHaveKey('model');
    expect($decoded)->toHaveKey('tools');
    expect($decoded['tools'])->toHaveKey('profile');
});

it('openclaw full config contains all squad agents', function () {
    $this->actingAs($this->user);

    Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Builder',
        'role' => 'Developer',
    ]);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    $config = $page->viewData('openclawFullConfig');
    $decoded = json_decode($config, true);

    expect($decoded)->toHaveKey('agents');
    expect($decoded['agents'])->toHaveCount(2);
});

it('workspace files contain agent-specific content', function () {
    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    $identityMd = $page->viewData('identityMd');
    expect($identityMd)->toContain('Scout');
    expect($identityMd)->toContain('Researcher');

    $agentsMd = $page->viewData('agentsMd');
    expect($agentsMd)->toContain('Team Agents');

    $toolsMd = $page->viewData('toolsMd');
    expect($toolsMd)->toContain('mission-control-heartbeat');
    expect($toolsMd)->toContain('mission-control-tasks');
});

it('passes lead agent to view for non-lead agents', function () {
    $lead = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Commander',
        'is_lead' => true,
    ]);

    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    $leadAgent = $page->viewData('leadAgent');
    expect($leadAgent)->not->toBeNull();
    expect($leadAgent->id)->toBe($lead->id);
});

it('passes null lead agent for lead agent setup', function () {
    $this->agent->update(['is_lead' => true]);
    $this->agent->refresh();

    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    expect($page->viewData('leadAgent'))->toBeNull();
});

it('shows main agent name for non-lead agent when lead exists', function () {
    Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Commander',
        'is_lead' => true,
    ]);

    $this->actingAs($this->user);

    Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent])
        ->assertSee('main agent (')
        ->assertSee('Commander')
        ->assertDontSee('It will delegate');
});

it('shows direct prompts for lead agent', function () {
    $this->agent->update(['is_lead' => true]);
    $this->agent->refresh();

    $this->actingAs($this->user);

    Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent])
        ->assertSee('Paste this into a chat with your')
        ->assertDontSee('main agent (');
});

it('only uses delegation language in step 8 for non-lead agents', function () {
    Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Commander',
        'is_lead' => true,
    ]);

    $this->actingAs($this->user);

    $html = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent])
        ->html();

    // Step 8 should have delegation language
    expect($html)->toContain('Tell Scout to run the mission-control-heartbeat skill');
    // Other steps should NOT have delegation language
    expect($html)->not->toContain('Tell Scout to add');
    expect($html)->not->toContain('Tell Scout to create');
    expect($html)->not->toContain('Tell Scout to save');
});

it('shows warning banner when lead agent is not connected', function () {
    $lead = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Commander',
        'is_lead' => true,
    ]);

    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    expect($page->viewData('leadNotReady'))->toBeTrue();
    $page->assertSee('Set up Commander')
        ->assertSee("agents/{$lead->id}/setup");
});

it('does not show warning banner when lead agent is connected', function () {
    Agent::factory()->online()->create([
        'team_id' => $this->team->id,
        'name' => 'Commander',
        'is_lead' => true,
    ]);

    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);
    expect($page->viewData('leadNotReady'))->toBeFalse();
});

it('does not show warning banner for lead agent setup page', function () {
    $this->agent->update(['is_lead' => true]);
    $this->agent->refresh();

    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);
    expect($page->viewData('leadNotReady'))->toBeFalse();
});

it('shows update config title for lead agent in step 2', function () {
    $this->agent->update(['is_lead' => true]);
    $this->agent->refresh();

    $this->actingAs($this->user);

    Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent])
        ->assertSee('Update Your Main Agent Configuration')
        ->assertDontSee('Add Agent to OpenClaw Gateway');
});

it('shows add agent title for non-lead agent in step 2', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent])
        ->assertSee('Add Agent to OpenClaw Gateway')
        ->assertDontSee('Update Your Main Agent Configuration');
});

it('provides lead config delta and example for lead agent', function () {
    $this->agent->update(['is_lead' => true]);
    $this->agent->refresh();

    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    expect($page->viewData('leadConfigDelta'))->not->toBeNull();
    expect($page->viewData('leadExampleConfig'))->not->toBeNull();

    $delta = json_decode($page->viewData('leadConfigDelta'), true);
    expect($delta)->toHaveKey('subagents');
    expect($delta)->toHaveKey('crons');
    expect($delta['subagents']['allowAgents'])->toBe(['*']);
});

it('does not provide lead config delta for non-lead agent', function () {
    $this->actingAs($this->user);

    $page = Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent]);

    expect($page->viewData('leadConfigDelta'))->toBeNull();
    expect($page->viewData('leadExampleConfig'))->toBeNull();
});

it('hides step 6 cron config for lead agent', function () {
    $this->agent->update(['is_lead' => true]);
    $this->agent->refresh();

    $this->actingAs($this->user);

    Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent])
        ->assertDontSee('Configure Heartbeat Cron');
});

it('shows step 6 cron config for non-lead agent', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Filament\Pages\AgentSetup::class, ['agent' => $this->agent])
        ->assertSee('Configure Heartbeat Cron');
});
