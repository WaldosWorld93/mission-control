<?php

use App\Models\AgentTemplate;
use App\Models\SquadTemplate;
use Database\Seeders\SquadTemplateSeeder;

it('seeds all four squad templates', function () {
    $this->seed(SquadTemplateSeeder::class);

    expect(SquadTemplate::count())->toBe(4);
});

it('seeds templates with correct agent counts', function () {
    $this->seed(SquadTemplateSeeder::class);

    $templates = SquadTemplate::with('agentTemplates')->get()->keyBy('name');

    expect($templates['Content Marketing Team']->agentTemplates)->toHaveCount(5)
        ->and($templates['Product Development Squad']->agentTemplates)->toHaveCount(5)
        ->and($templates['Research & Analysis Team']->agentTemplates)->toHaveCount(4)
        ->and($templates['Customer Support Squad']->agentTemplates)->toHaveCount(4);
});

it('marks all templates as public', function () {
    $this->seed(SquadTemplateSeeder::class);

    expect(SquadTemplate::where('is_public', true)->count())->toBe(4);
});

it('sets estimated daily costs', function () {
    $this->seed(SquadTemplateSeeder::class);

    SquadTemplate::all()->each(function ($template) {
        expect($template->estimated_daily_cost)->not->toBeNull();
    });
});

it('sets exactly one lead per template', function () {
    $this->seed(SquadTemplateSeeder::class);

    SquadTemplate::with('agentTemplates')->get()->each(function ($template) {
        $leads = $template->agentTemplates->where('is_lead', true);
        expect($leads)->toHaveCount(1);
    });
});

it('populates soul_md_template for all agents', function () {
    $this->seed(SquadTemplateSeeder::class);

    AgentTemplate::all()->each(function ($agent) {
        expect($agent->soul_md_template)->not->toBeNull()->not->toBeEmpty();
    });
});

it('sets heartbeat and work models for all agents', function () {
    $this->seed(SquadTemplateSeeder::class);

    AgentTemplate::all()->each(function ($agent) {
        expect($agent->heartbeat_model)->not->toBeNull()
            ->and($agent->work_model)->not->toBeNull();
    });
});

it('sets heartbeat intervals for all agents', function () {
    $this->seed(SquadTemplateSeeder::class);

    AgentTemplate::all()->each(function ($agent) {
        expect($agent->heartbeat_interval_seconds)->toBeGreaterThan(0);
    });
});

it('is idempotent — running twice does not duplicate templates', function () {
    $this->seed(SquadTemplateSeeder::class);
    $this->seed(SquadTemplateSeeder::class);

    expect(SquadTemplate::count())->toBe(4)
        ->and(AgentTemplate::count())->toBe(18);
});
