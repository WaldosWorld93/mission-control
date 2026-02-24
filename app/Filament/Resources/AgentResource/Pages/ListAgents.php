<?php

namespace App\Filament\Resources\AgentResource\Pages;

use App\Filament\Resources\AgentResource;
use App\Models\Agent;
use App\Models\AgentTemplate;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('addFromTemplate')
                ->label('Add from Template')
                ->icon('heroicon-o-square-3-stack-3d')
                ->color('gray')
                ->form([
                    Forms\Components\Select::make('agent_template_id')
                        ->label('Agent Template')
                        ->options(function () {
                            return AgentTemplate::query()
                                ->whereHas('squadTemplate', fn ($q) => $q->where('is_public', true))
                                ->with('squadTemplate')
                                ->get()
                                ->sortBy([
                                    fn ($a, $b) => $a->squadTemplate->name <=> $b->squadTemplate->name,
                                    fn ($a, $b) => $a->sort_order <=> $b->sort_order,
                                ])
                                ->mapWithKeys(fn (AgentTemplate $t) => [
                                    $t->id => $t->squadTemplate->name.' — '.$t->name.' ('.$t->role.')',
                                ]);
                        })
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Choose a pre-configured agent role from an existing squad template.'),
                ])
                ->action(function (array $data): void {
                    $template = AgentTemplate::findOrFail($data['agent_template_id']);
                    $team = auth()->user()->currentTeam;

                    if (! $team) {
                        Notification::make()
                            ->title('No team found')
                            ->body('You must belong to a team before adding an agent.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $plainToken = Str::random(40);

                    $agent = Agent::create([
                        'team_id' => $team->id,
                        'name' => $template->name,
                        'role' => $template->role,
                        'description' => $template->description,
                        'soul_md' => $template->soul_md_template,
                        'soul_hash' => hash('sha256', $template->soul_md_template ?? ''),
                        'status' => 'offline',
                        'is_lead' => $template->is_lead,
                        'skills' => $template->default_skills ?? [],
                        'heartbeat_model' => $template->heartbeat_model,
                        'work_model' => $template->work_model,
                        'api_token' => hash('sha256', $plainToken),
                        'metadata' => [],
                        'consecutive_errors' => 0,
                        'is_paused' => false,
                    ]);

                    session()->put('deployed_tokens', [[
                        'name' => $agent->name,
                        'token' => $plainToken,
                    ]]);

                    Notification::make()
                        ->title('Agent created')
                        ->body("{$agent->name} has been created from the {$template->squadTemplate->name} template.")
                        ->success()
                        ->send();

                    $this->redirect(url("agents/{$agent->id}/setup"), navigate: true);
                })
                ->modalHeading('Add Agent from Template')
                ->modalSubmitActionLabel('Create Agent'),
            Actions\CreateAction::make()
                ->label('Add Custom Agent'),
        ];
    }
}
