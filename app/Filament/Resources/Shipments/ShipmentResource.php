<?php

namespace App\Filament\Resources\Shipments;

use App\Filament\Concerns\HasDateRangeFilter;
use App\Filament\Resources\Shipments\Pages\ManageShipments;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\SupplierProfile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ShipmentResource extends Resource
{
    use HasDateRangeFilter;

    protected static ?string $model = Shipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static UnitEnum|string|null $navigationGroup = 'Commerce';

    protected static ?string $navigationLabel = 'Logistics';

    protected static ?string $recordTitleAttribute = 'tracking_reference';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')->label('Order')->searchable(),
                TextColumn::make('supplierProfile.business_name')->label('Supplier')->searchable(),
                BadgeColumn::make('status')->colors([
                    'warning' => ['pending', 'confirmed', 'processing'],
                    'info' => ['shipped', 'out_for_delivery'],
                    'success' => 'delivered',
                    'danger' => ['cancelled', 'refunded'],
                ]),
                TextColumn::make('carrier')->placeholder('—'),
                TextColumn::make('tracking_reference')->label('Tracking #')->placeholder('—')->copyable(),
                TextColumn::make('dispatched_at')->label('Dispatched')->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('expected_delivery_at')->label('Expected')->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('delivered_at')->label('Delivered')->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('on_time')
                    ->label('On Time')
                    ->state(fn (Shipment $record) => match ($record->isOnTime()) {
                        true => 'Yes',
                        false => 'No',
                        null => '—',
                    })
                    ->badge()
                    ->color(fn (Shipment $record) => match ($record->isOnTime()) {
                        true => 'success',
                        false => 'danger',
                        null => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(array_combine(Order::STATUSES, array_map('ucfirst', Order::STATUSES))),
                SelectFilter::make('supplier_profile_id')
                    ->label('Supplier')
                    ->options(fn () => SupplierProfile::orderBy('business_name')->pluck('business_name', 'id')),
                static::dateRangeFilter('delivered_at', 'Delivered'),
            ])
            ->recordActions([
                ViewAction::make()->schema([
                    Section::make('Shipment')->schema([
                        TextEntry::make('order.order_number')->label('Order'),
                        TextEntry::make('supplierProfile.business_name')->label('Supplier'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('carrier')->placeholder('—'),
                        TextEntry::make('tracking_reference')->label('Tracking #')->placeholder('—'),
                        TextEntry::make('dispatched_at')->dateTime()->placeholder('—'),
                        TextEntry::make('expected_delivery_at')->label('Expected delivery')->dateTime()->placeholder('—'),
                        TextEntry::make('delivered_at')->dateTime()->placeholder('—'),
                        TextEntry::make('proof_of_delivery_note')->label('Proof of delivery')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                    ])->columns(2),
                ]),
                Action::make('updateLogistics')
                    ->label('Logistics Info')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('gray')
                    ->schema([
                        TextInput::make('carrier')->maxLength(255),
                        TextInput::make('tracking_reference')->label('Tracking reference')->maxLength(255),
                        DateTimePicker::make('expected_delivery_at')->label('Expected delivery date')->native(false),
                        Textarea::make('notes')->label('Internal notes (not shown to the customer)'),
                    ])
                    ->fillForm(fn (Shipment $record) => [
                        'carrier' => $record->carrier,
                        'tracking_reference' => $record->tracking_reference,
                        'expected_delivery_at' => $record->expected_delivery_at,
                        'notes' => $record->notes,
                    ])
                    ->action(function (Shipment $record, array $data) {
                        $record->update($data);
                        Notification::make()->title('Logistics info updated')->success()->send();
                    }),
                Action::make('markDispatched')
                    ->label('Mark Dispatched')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('info')
                    ->visible(fn (Shipment $record) => ! in_array($record->status, ['shipped', 'out_for_delivery', 'delivered'], true))
                    ->requiresConfirmation()
                    ->action(function (Shipment $record) {
                        $record->update([
                            'status' => 'shipped',
                            'dispatched_at' => $record->dispatched_at ?? now(),
                        ]);
                        Notification::make()->title('Shipment marked dispatched')->success()->send();
                    }),
                Action::make('markDelivered')
                    ->label('Mark Delivered')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Shipment $record) => $record->status !== 'delivered')
                    ->schema([
                        Textarea::make('proof_of_delivery_note')->label('Proof of delivery (recipient name, notes, etc.)'),
                    ])
                    ->action(function (Shipment $record, array $data) {
                        $record->update([
                            'status' => 'delivered',
                            'delivered_at' => $record->delivered_at ?? now(),
                            'proof_of_delivery_note' => $data['proof_of_delivery_note'] ?: $record->proof_of_delivery_note,
                        ]);
                        Notification::make()->title('Shipment marked delivered')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageShipments::route('/'),
        ];
    }
}
