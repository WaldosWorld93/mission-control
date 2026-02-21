<?php

namespace App\Filament\Resources;

use App\Enums\ProjectStatus;
use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Project Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                        Forms\Components\ColorPicker::make('color')
                            ->label('Project Color'),
                        Forms\Components\Select::make('status')
                            ->options(ProjectStatus::class)
                            ->default(ProjectStatus::Active)
                            ->required(),
                    ])->columns(2),
                Forms\Components\Section::make('Team')
                    ->schema([
                        Forms\Components\Select::make('lead_agent_id')
                            ->label('Lead Agent')
                            ->relationship('leadAgent', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('agents')
                            ->relationship('agents', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color')
                    ->label('')
                    ->sortable(false),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (ProjectStatus $state): string => match ($state) {
                        ProjectStatus::Active => 'success',
                        ProjectStatus::Paused => 'warning',
                        ProjectStatus::Completed => 'info',
                        ProjectStatus::Archived => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('leadAgent.name')
                    ->label('Lead Agent')
                    ->placeholder('None'),
                Tables\Columns\TextColumn::make('agents_count')
                    ->counts('agents')
                    ->label('Agents')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tasks_count')
                    ->counts('tasks')
                    ->label('Tasks')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ProjectStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('board')
                    ->label('Board')
                    ->icon('heroicon-o-view-columns')
                    ->url(fn (Project $record): string => url("projects/{$record->id}/board")),
                Tables\Actions\Action::make('messages')
                    ->label('Messages')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (Project $record): string => url("projects/{$record->id}/messages")),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No projects yet')
            ->emptyStateDescription('Create a project to start organizing your agents\' work.')
            ->emptyStateIcon('heroicon-o-folder');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
