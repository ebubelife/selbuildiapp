<?php

namespace App\Filament\Resources\DeliveryAgents;

use App\Filament\Concerns\HasDateRangeFilter;
use App\Filament\Resources\DeliveryAgents\Pages\ManageDeliveryAgents;
use App\Models\DeliveryAgent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class DeliveryAgentResource extends Resource
{
    use HasDateRangeFilter;

    protected static ?string $model = DeliveryAgent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static UnitEnum|string|null $navigationGroup = 'Verification';

    protected static ?string $navigationLabel = 'Delivery Agents';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->maxLength(255),
            TextInput::make('phone')->label('Phone (primary)')->required()->maxLength(30),
            TextInput::make('phone_2')->label('Phone (secondary, optional)')->maxLength(30),
            TextInput::make('vehicle_id')
                ->label('Vehicle ID / Plate Number')
                ->maxLength(255)
                ->helperText('Motorcycle, van, or truck registration - whatever identifies the vehicle they actually deliver with.'),
            FileUpload::make('document_path')
                ->label("Driver's License / ID Document")
                ->disk('local')
                ->directory('delivery-agent-documents')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                ->maxSize(5120),
            Toggle::make('is_active')
                ->label('Active')
                ->helperText('An inactive agent can no longer be assigned to new shipments.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->label('Phone'),
                TextColumn::make('vehicle_id')->label('Vehicle')->placeholder('—'),
                TextColumn::make('shipments_count')->counts('shipments')->label('Deliveries'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Added')->date()->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
                static::dateRangeFilter('created_at', 'Added'),
            ])
            ->recordActions([
                Action::make('downloadDocument')
                    ->label('Document')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('gray')
                    ->visible(fn (DeliveryAgent $record) => filled($record->document_path))
                    ->action(fn (DeliveryAgent $record) => Storage::disk('local')->download(
                        $record->document_path,
                        $record->name.'-id.'.pathinfo($record->document_path, PATHINFO_EXTENSION)
                    )),
                Action::make('toggleActive')
                    ->label(fn (DeliveryAgent $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (DeliveryAgent $record) => $record->is_active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                    ->color(fn (DeliveryAgent $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (DeliveryAgent $record) {
                        $record->update(['is_active' => ! $record->is_active]);
                        Notification::make()
                            ->title($record->is_active ? 'Agent activated' : 'Agent deactivated')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDeliveryAgents::route('/'),
        ];
    }
}
