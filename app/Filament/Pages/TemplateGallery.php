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
        $user = auth()->user();
        $team = $user->currentTeam;

        if (! $team) {
            $team = $user->teams()->first() ?? $user->ownedTeams()->first();

            if (! $team) {
                Notification::make()
                    ->title('No team found')
                    ->body('You must belong to a team before deploying a template.')
                    ->danger()
                    ->send();

                return;
            }

            $user->update(['current_team_id' => $team->id]);
        }

        $tokens = [];
        $leadAgentId = null;

        DB::transaction(function () use ($template, $team, &$tokens, &$leadAgentId) {
            $project = Project::create([
                'team_id' => $team->id,
                'name' => $template->name,
                'slug' => Str::slug($template->name).'-'.Str::random(4),
                'description' => $template->description,
                'status' => 'active',
                'color' => $this->projectColor($template->name),
                'started_at' => now(),
            ]);

            foreach ($template->agentTemplates as $agentTemplate) {
                $agent = new Agent([
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
                    'metadata' => [],
                    'consecutive_errors' => 0,
                    'is_paused' => false,
                ]);

                $plainToken = $agent->generateApiToken();
                $agent->save();

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

        session()->put('deployed_tokens', $tokens);
        session()->put('deployed_template', $template->name);

        if ($leadAgentId) {
            $this->redirect(url("agents/{$leadAgentId}/setup"), navigate: true);
        } else {
            $this->redirect(url('setup/squad'), navigate: true);
        }
    }

    private function projectColor(string $name): string
    {
        $colors = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#3b82f6'];

        return $colors[crc32($name) % count($colors)];
    }
}
