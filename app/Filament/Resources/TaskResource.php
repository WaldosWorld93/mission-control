<?php

namespace App\Filament\Resources;

use App\Enums\AttemptStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Resources\TaskResource\Pages;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Task Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('project_id')
                            ->label('Project')
                            ->relationship('project', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('assigned_agent_id')
                            ->label('Assigned Agent')
                            ->relationship(
                                'assignedAgent',
                                'name',
                                fn (Forms\Components\Select $component, Forms\Get $get, $query) => $get('project_id')
                                    ? $query->whereHas('projects', fn ($q) => $q->where('projects.id', $get('project_id')))
                                    : $query,
                            )
                            ->searchable()
                            ->preload(),
                    ])->columns(2),
                Forms\Components\Section::make('Classification')
                    ->schema([
                        Forms\Components\Select::make('priority')
                            ->options(TaskPriority::class)
                            ->default(TaskPriority::Medium)
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options(TaskStatus::class)
                            ->default(TaskStatus::Backlog)
                            ->required(),
                        Forms\Components\Select::make('parent_task_id')
                            ->label('Parent Task')
                            ->relationship('parent', 'title')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('dependencies')
                            ->relationship('dependencies', 'title')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ])->columns(2),
                Forms\Components\Section::make('Additional')
                    ->schema([
                        Forms\Components\TagsInput::make('tags'),
                        Forms\Components\DateTimePicker::make('due_at')
                            ->label('Due Date'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (TaskPriority $state): string => match ($state) {
                        TaskPriority::Critical => 'danger',
                        TaskPriority::High => 'warning',
                        TaskPriority::Medium => 'info',
                        TaskPriority::Low => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (TaskStatus $state): string => match ($state) {
                        TaskStatus::Blocked => 'danger',
                        TaskStatus::Backlog => 'gray',
                        TaskStatus::Assigned => 'info',
                        TaskStatus::InProgress => 'warning',
                        TaskStatus::InReview => 'info',
                        TaskStatus::Done => 'success',
                        TaskStatus::Cancelled => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignedAgent.name')
                    ->label('Agent')
                    ->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Project')
                    ->sortable(),
                Tables\Columns\TextColumn::make('dependencies_count')
                    ->counts('dependencies')
                    ->label('Deps')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subtasks_count')
                    ->counts('subtasks')
                    ->label('Subtasks')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(TaskStatus::class),
                Tables\Filters\SelectFilter::make('priority')
                    ->options(TaskPriority::class),
                Tables\Filters\SelectFilter::make('assigned_agent_id')
                    ->label('Agent')
                    ->relationship('assignedAgent', 'name'),
                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Project')
                    ->relationship('project', 'name'),
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
            ->defaultSort('updated_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Tabs::make('Task')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Details')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Infolists\Components\Section::make()
                                    ->schema([
                                        Infolists\Components\TextEntry::make('title')
                                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                            ->weight('bold')
                                            ->columnSpanFull(),
                                        Infolists\Components\TextEntry::make('description')
                                            ->columnSpanFull()
                                            ->placeholder('No description.'),
                                    ]),
                                Infolists\Components\Section::make('Status')
                                    ->schema([
                                        Infolists\Components\Grid::make(4)
                                            ->schema([
                                                Infolists\Components\TextEntry::make('status')
                                                    ->badge()
                                                    ->color(fn (TaskStatus $state): string => match ($state) {
                                                        TaskStatus::Blocked => 'danger',
                                                        TaskStatus::Backlog => 'gray',
                                                        TaskStatus::Assigned => 'info',
                                                        TaskStatus::InProgress => 'warning',
                                                        TaskStatus::InReview => 'info',
                                                        TaskStatus::Done => 'success',
                                                        TaskStatus::Cancelled => 'gray',
                                                    }),
                                                Infolists\Components\TextEntry::make('priority')
                                                    ->badge()
                                                    ->color(fn (TaskPriority $state): string => match ($state) {
                                                        TaskPriority::Critical => 'danger',
                                                        TaskPriority::High => 'warning',
                                                        TaskPriority::Medium => 'info',
                                                        TaskPriority::Low => 'gray',
                                                    }),
                                                Infolists\Components\TextEntry::make('assignedAgent.name')
                                                    ->label('Assigned Agent')
                                                    ->placeholder('Unassigned'),
                                                Infolists\Components\TextEntry::make('project.name')
                                                    ->label('Project'),
                                            ]),
                                    ]),
                                Infolists\Components\Section::make('Dates')
                                    ->schema([
                                        Infolists\Components\Grid::make(4)
                                            ->schema([
                                                Infolists\Components\TextEntry::make('created_at')
                                                    ->dateTime(),
                                                Infolists\Components\TextEntry::make('started_at')
                                                    ->dateTime()
                                                    ->placeholder('Not started'),
                                                Infolists\Components\TextEntry::make('completed_at')
                                                    ->dateTime()
                                                    ->placeholder('Not completed'),
                                                Infolists\Components\TextEntry::make('due_at')
                                                    ->label('Due Date')
                                                    ->dateTime()
                                                    ->placeholder('No due date'),
                                            ]),
                                    ]),
                                Infolists\Components\Section::make('Dependencies')
                                    ->schema([
                                        Infolists\Components\RepeatableEntry::make('dependencies')
                                            ->label('Blocked By')
                                            ->schema([
                                                Infolists\Components\TextEntry::make('title'),
                                                Infolists\Components\TextEntry::make('status')
                                                    ->badge(),
                                            ])
                                            ->columns(2)
                                            ->placeholder('No dependencies.'),
                                        Infolists\Components\RepeatableEntry::make('dependents')
                                            ->label('Blocking')
                                            ->schema([
                                                Infolists\Components\TextEntry::make('title'),
                                                Infolists\Components\TextEntry::make('status')
                                                    ->badge(),
                                            ])
                                            ->columns(2)
                                            ->placeholder('No dependents.'),
                                    ]),
                            ]),
                        Infolists\Components\Tabs\Tab::make('Attempts')
                            ->icon('heroicon-o-arrow-path')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('attempts')
                                    ->label('')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('agent.name')
                                            ->label('Agent'),
                                        Infolists\Components\TextEntry::make('status')
                                            ->badge()
                                            ->color(fn (AttemptStatus $state): string => match ($state) {
                                                AttemptStatus::Active => 'warning',
                                                AttemptStatus::Completed => 'success',
                                                AttemptStatus::Failed => 'danger',
                                                AttemptStatus::Reassigned => 'info',
                                                AttemptStatus::TimedOut => 'danger',
                                            }),
                                        Infolists\Components\TextEntry::make('started_at')
                                            ->dateTime(),
                                        Infolists\Components\TextEntry::make('ended_at')
                                            ->dateTime()
                                            ->placeholder('In progress'),
                                        Infolists\Components\TextEntry::make('error_message')
                                            ->placeholder('None')
                                            ->columnSpanFull()
                                            ->visible(fn ($record): bool => $record->error_message !== null),
                                    ])
                                    ->columns(4)
                                    ->placeholder('No attempts yet.'),
                            ]),
                        Infolists\Components\Tabs\Tab::make('Artifacts')
                            ->icon('heroicon-o-paper-clip')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('artifacts')
                                    ->label('')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('display_name')
                                            ->label('File'),
                                        Infolists\Components\TextEntry::make('artifact_type')
                                            ->label('Type')
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('size_bytes')
                                            ->label('Size')
                                            ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024, 1).' KB' : '—'),
                                        Infolists\Components\TextEntry::make('version')
                                            ->label('v'),
                                    ])
                                    ->columns(4)
                                    ->placeholder('No artifacts.'),
                            ]),
                        Infolists\Components\Tabs\Tab::make('Conversation')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Infolists\Components\ViewEntry::make('thread')
                                    ->label('')
                                    ->view('filament.resources.task-resource.thread-embed')
                                    ->visible(fn (Task $record): bool => $record->thread !== null),
                                Infolists\Components\Placeholder::make('no_thread')
                                    ->label('')
                                    ->content('No conversation thread linked to this task.')
                                    ->visible(fn (Task $record): bool => $record->thread === null),
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
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'view' => Pages\ViewTask::route('/{record}'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
        ];
    }
}
