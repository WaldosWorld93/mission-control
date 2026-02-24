<?php

namespace App\Filament\Resources;

use App\Enums\AgentStatus;
use App\Filament\Resources\AgentResource\Pages;
use App\Models\Agent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Agent Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('role')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                    ])->columns(2),
                Forms\Components\Section::make('Models')
                    ->schema([
                        Forms\Components\Select::make('heartbeat_model')
                            ->options([
                                'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
                                'claude-haiku-4-20250414' => 'Claude Haiku 4',
                                'gpt-4o' => 'GPT-4o',
                                'gpt-4o-mini' => 'GPT-4o Mini',
                            ]),
                        Forms\Components\Select::make('work_model')
                            ->options([
                                'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
                                'claude-opus-4-20250514' => 'Claude Opus 4',
                                'gpt-4o' => 'GPT-4o',
                                'o1' => 'o1',
                            ]),
                    ])->columns(2),
                Forms\Components\Section::make('Projects')
                    ->schema([
                        Forms\Components\Select::make('projects')
                            ->relationship('projects', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ]),
                Forms\Components\Section::make('SOUL.md')
                    ->schema([
                        Forms\Components\Textarea::make('soul_md')
                            ->label('SOUL.md')
                            ->helperText('Define your agent\'s identity and working style. Leave blank to auto-generate from the name, role, and description above.')
                            ->rows(15)
                            ->extraAttributes([
                                'style' => "font-family: 'JetBrains Mono', monospace; font-size: 13px;",
                            ])
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('soul_hash')
                            ->label('Soul Hash')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ViewColumn::make('name')
                    ->view('filament.tables.columns.agent-name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (AgentStatus $state): string => match ($state) {
                        AgentStatus::Online => 'success',
                        AgentStatus::Busy => 'info',
                        AgentStatus::Idle => 'warning',
                        AgentStatus::Offline => 'danger',
                        AgentStatus::Error => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_heartbeat_at')
                    ->label('Last Heartbeat')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('projects_count')
                    ->counts('projects')
                    ->label('Projects')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(AgentStatus::class),
                Tables\Filters\TernaryFilter::make('is_paused')
                    ->label('Paused'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->recordClasses(fn (Agent $record) => $record->is_paused ? 'row-paused' : '')
            ->emptyStateHeading('No agents yet')
            ->emptyStateDescription('Create an agent to start coordinating your AI workforce.')
            ->emptyStateIcon('heroicon-o-cpu-chip');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Tabs::make('Agent')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Overview')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Infolists\Components\Section::make()
                                    ->schema([
                                        Infolists\Components\Grid::make(3)
                                            ->schema([
                                                Infolists\Components\TextEntry::make('name')
                                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                                    ->weight('bold'),
                                                Infolists\Components\TextEntry::make('role'),
                                                Infolists\Components\TextEntry::make('status')
                                                    ->badge()
                                                    ->color(fn (AgentStatus $state): string => match ($state) {
                                                        AgentStatus::Online => 'success',
                                                        AgentStatus::Busy => 'info',
                                                        AgentStatus::Idle => 'warning',
                                                        AgentStatus::Offline => 'danger',
                                                        AgentStatus::Error => 'danger',
                                                    }),
                                            ]),
                                        Infolists\Components\TextEntry::make('description')
                                            ->columnSpanFull(),
                                    ]),
                                Infolists\Components\Section::make('Models & Config')
                                    ->schema([
                                        Infolists\Components\Grid::make(2)
                                            ->schema([
                                                Infolists\Components\TextEntry::make('heartbeat_model')
                                                    ->label('Heartbeat Model')
                                                    ->placeholder('Not set'),
                                                Infolists\Components\TextEntry::make('work_model')
                                                    ->label('Work Model')
                                                    ->placeholder('Not set'),
                                            ]),
                                    ]),
                                Infolists\Components\Section::make('Health')
                                    ->schema([
                                        Infolists\Components\Grid::make(3)
                                            ->schema([
                                                Infolists\Components\TextEntry::make('last_heartbeat_at')
                                                    ->label('Last Heartbeat')
                                                    ->since()
                                                    ->placeholder('Never'),
                                                Infolists\Components\TextEntry::make('consecutive_errors')
                                                    ->label('Consecutive Errors')
                                                    ->badge()
                                                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
                                                Infolists\Components\TextEntry::make('is_paused')
                                                    ->label('Paused')
                                                    ->badge()
                                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                                                    ->color(fn (bool $state): string => $state ? 'danger' : 'success'),
                                            ]),
                                        Infolists\Components\TextEntry::make('paused_reason')
                                            ->label('Paused Reason')
                                            ->visible(fn (Agent $record): bool => $record->is_paused)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Infolists\Components\Tabs\Tab::make('Tasks')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('assignedTasks')
                                    ->label('')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('title'),
                                        Infolists\Components\TextEntry::make('status')
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('priority')
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('project.name')
                                            ->label('Project'),
                                    ])
                                    ->columns(4)
                                    ->placeholder('No tasks assigned.'),
                            ]),
                        Infolists\Components\Tabs\Tab::make('Projects')
                            ->icon('heroicon-o-folder')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('projects')
                                    ->label('')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name'),
                                        Infolists\Components\TextEntry::make('status')
                                            ->badge(),
                                    ])
                                    ->columns(2)
                                    ->placeholder('No projects assigned.'),
                            ]),
                        Infolists\Components\Tabs\Tab::make('SOUL.md')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Infolists\Components\TextEntry::make('soul_md')
                                    ->label('')
                                    ->markdown()
                                    ->extraAttributes(['class' => 'font-mono'])
                                    ->placeholder('No SOUL.md configured.'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgents::route('/'),
            'create' => Pages\CreateAgent::route('/create'),
            'view' => Pages\ViewAgent::route('/{record}'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
        ];
    }
}
