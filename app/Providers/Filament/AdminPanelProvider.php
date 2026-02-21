<?php

namespace App\Providers\Filament;

use App\Enums\AgentStatus;
use App\Filament\Resources\AgentResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\TaskResource;
use App\Models\Agent;
use App\Models\Project;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('')
            ->login()
            ->registration()
            ->brandName('Mission Control')
            ->colors([
                'primary' => Color::Indigo,
                'danger' => Color::Rose,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'info' => Color::Sky,
                'gray' => Color::Slate,
            ])
            ->font('Inter')
            ->darkMode()
            ->sidebarCollapsibleOnDesktop()
            ->theme(asset('css/filament/admin/theme.css'))
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render('<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">'),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                $builder->groups([
                    NavigationGroup::make('Overview')
                        ->items([
                            NavigationItem::make('Home')
                                ->icon('heroicon-o-home')
                                ->isActiveWhen(fn (): bool => request()->routeIs('filament.app.pages.home'))
                                ->url(url('home')),
                        ]),
                    NavigationGroup::make('Projects')
                        ->items($this->buildProjectNavItems()),
                    NavigationGroup::make('Management')
                        ->items([
                            NavigationItem::make('Agents')
                                ->icon('heroicon-o-cpu-chip')
                                ->badge($this->getAgentBadge())
                                ->isActiveWhen(fn (): bool => request()->routeIs('filament.app.resources.agents.*'))
                                ->url(AgentResource::getUrl()),
                            NavigationItem::make('Tasks')
                                ->icon('heroicon-o-clipboard-document-list')
                                ->isActiveWhen(fn (): bool => request()->routeIs('filament.app.resources.tasks.*'))
                                ->url(TaskResource::getUrl()),
                        ]),
                ]);

                return $builder;
            })
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\SetFilamentTeamContext::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * @return array<NavigationItem>
     */
    private function buildProjectNavItems(): array
    {
        $items = [];

        try {
            $projects = Project::where('status', 'active')
                ->orderBy('name')
                ->limit(20)
                ->get();

            foreach ($projects as $project) {
                $items[] = NavigationItem::make($project->name)
                    ->icon('heroicon-o-folder')
                    ->isActiveWhen(fn (): bool => request()->is("projects/{$project->id}/*"))
                    ->url(url("projects/{$project->id}/board"))
                    ->childItems([
                        NavigationItem::make('Board')
                            ->icon('heroicon-o-view-columns')
                            ->url(url("projects/{$project->id}/board"))
                            ->isActiveWhen(fn (): bool => request()->is("projects/{$project->id}/board")),
                        NavigationItem::make('Messages')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->url(url("projects/{$project->id}/messages"))
                            ->isActiveWhen(fn (): bool => request()->is("projects/{$project->id}/messages")),
                    ]);
            }
        } catch (\Throwable) {
            // Gracefully handle if DB isn't available yet
        }

        $items[] = NavigationItem::make('All Projects')
            ->icon('heroicon-o-plus-circle')
            ->isActiveWhen(fn (): bool => request()->routeIs('filament.app.resources.projects.*'))
            ->url(ProjectResource::getUrl());

        return $items;
    }

    private function getAgentBadge(): ?string
    {
        try {
            $online = Agent::whereIn('status', [AgentStatus::Online, AgentStatus::Busy])->count();
            $total = Agent::count();

            if ($total === 0) {
                return null;
            }

            return $online.'/'.$total;
        } catch (\Throwable) {
            return null;
        }
    }
}
