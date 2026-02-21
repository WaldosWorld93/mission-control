<?php

namespace App\Filament\Pages;

use App\Models\Agent;
use App\Models\Project;
use App\Models\SquadTemplate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TemplateGallery extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationLabel = 'Templates';

    protected static ?string $title = 'Squad Templates';

    protected static ?string $slug = 'templates';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.template-gallery';

    public ?int $expandedTemplateId = null;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'templates' => SquadTemplate::query()
                ->where('is_public', true)
                ->with('agentTemplates')
                ->get(),
        ];
    }

    public function toggleTemplate(int $templateId): void
    {
        $this->expandedTemplateId = $this->expandedTemplateId === $templateId
            ? null
            : $templateId;
    }

    public function deploy(int $templateId): void
    {
        $template = SquadTemplate::with('agentTemplates')->findOrFail($templateId);
        $team = auth()->user()->currentTeam;

        if (! $team) {
            Notification::make()
                ->title('No team selected')
                ->body('Please select a team before deploying a template.')
                ->danger()
                ->send();

            return;
        }

        $tokens = [];

        DB::transaction(function () use ($template, $team, &$tokens) {
            $project = Project::create([
                'team_id' => $team->id,
                'name' => $template->name,
                'slug' => Str::slug($template->name).'-'.Str::random(4),
                'description' => $template->description,
                'status' => 'active',
                'color' => $this->projectColor($template->name),
                'started_at' => now(),
            ]);

            $leadAgentId = null;

            foreach ($template->agentTemplates as $agentTemplate) {
                $plainToken = Str::random(40);

                $agent = Agent::create([
                    'team_id' => $team->id,
                    'name' => $agentTemplate->name,
                    'role' => $agentTemplate->role,
                    'description' => $agentTemplate->description,
                    'soul_md' => $agentTemplate->soul_md_template,
                    'soul_hash' => hash('sha256', $agentTemplate->soul_md_template ?? ''),
                    'status' => 'offline',
                    'is_lead' => $agentTemplate->is_lead,
                    'skills' => $agentTemplate->default_skills ?? [],
                    'heartbeat_model' => $agentTemplate->heartbeat_model,
                    'work_model' => $agentTemplate->work_model,
                    'api_token' => hash('sha256', $plainToken),
                    'metadata' => [],
                    'consecutive_errors' => 0,
                    'is_paused' => false,
                ]);

                $project->agents()->attach($agent->id, [
                    'joined_at' => now(),
                ]);

                if ($agentTemplate->is_lead) {
                    $leadAgentId = $agent->id;
                }

                $tokens[] = [
                    'name' => $agent->name,
                    'token' => $plainToken,
                ];
            }

            if ($leadAgentId) {
                $project->update(['lead_agent_id' => $leadAgentId]);
            }
        });

        session()->flash('deployed_tokens', $tokens);
        session()->flash('deployed_template', $template->name);

        $this->redirect(url('templates/deployed'), navigate: true);
    }

    private function projectColor(string $name): string
    {
        $colors = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#3b82f6'];

        return $colors[crc32($name) % count($colors)];
    }
}
