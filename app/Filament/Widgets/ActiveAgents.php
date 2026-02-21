<?php

namespace App\Filament\Widgets;

use App\Enums\AgentStatus;
use App\Models\Agent;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ActiveAgents extends BaseWidget
{
    protected static ?string $heading = 'Agents';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Agent::query()
                    ->with(['assignedTasks' => fn ($q) => $q->where('status', 'in_progress')])
                    ->orderByRaw("FIELD(status, 'online', 'busy', 'idle', 'error', 'offline')")
            )
            ->columns([
                Tables\Columns\ViewColumn::make('name')
                    ->label('Agent')
                    ->view('filament.tables.columns.agent-name'),
                Tables\Columns\TextColumn::make('role')
                    ->color('gray'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => AgentStatus::Online->value,
                        'warning' => fn ($state): bool => in_array($state, [AgentStatus::Idle->value, AgentStatus::Busy->value]),
                        'danger' => fn ($state): bool => in_array($state, [AgentStatus::Error->value, AgentStatus::Offline->value]),
                    ]),
                Tables\Columns\TextColumn::make('current_task')
                    ->label('Current Task')
                    ->getStateUsing(fn (Agent $record): ?string => $record->assignedTasks->first()?->title)
                    ->placeholder('—')
                    ->limit(40),
                Tables\Columns\TextColumn::make('last_heartbeat_at')
                    ->label('Last Seen')
                    ->since()
                    ->placeholder('Never'),
            ])
            ->paginated(false);
    }
}
