<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Concerns\HasDateRangeFilter;
use App\Filament\Resources\ActivityLogs\Pages\ManageActivityLogs;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ActivityLogResource extends Resource
{
    use HasDateRangeFilter;

    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static UnitEnum|string|null $navigationGroup = 'Verification';

    protected static ?string $navigationLabel = 'Notifications';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->state(fn (ActivityLog $record) => $record->typeLabel())
                    ->badge(),
                TextColumn::make('description')->wrap(),
                TextColumn::make('causer.name')->label('By')->placeholder('System'),
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')->options(ActivityLog::TYPES),
                static::dateRangeFilter('created_at', 'When'),
            ])
            ->recordActions([
                ViewAction::make()->schema([
                    Section::make('Activity')->schema([
                        TextEntry::make('type')->state(fn (ActivityLog $record) => $record->typeLabel())->badge(),
                        TextEntry::make('description')->columnSpanFull(),
                        TextEntry::make('causer.name')->label('By')->placeholder('System'),
                        TextEntry::make('created_at')->label('When')->dateTime(),
                        TextEntry::make('data')
                            ->label('Details')
                            ->state(fn (ActivityLog $record) => $record->data ? json_encode($record->data, JSON_PRETTY_PRINT) : null)
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])->columns(2),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageActivityLogs::route('/'),
        ];
    }
}
