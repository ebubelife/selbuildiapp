<?php

namespace App\Filament\Resources\DeliveryUpdateRequests;

use App\Filament\Concerns\HasDateRangeFilter;
use App\Filament\Resources\DeliveryUpdateRequests\Pages\ManageDeliveryUpdateRequests;
use App\Models\ActivityLog;
use App\Models\ShipmentUpdateRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DeliveryUpdateRequestResource extends Resource
{
    use HasDateRangeFilter;

    protected static ?string $model = ShipmentUpdateRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static UnitEnum|string|null $navigationGroup = 'Commerce';

    protected static ?string $navigationLabel = 'Delivery Updates';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ShipmentUpdateRequest::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('shipment.order.order_number')->label('Order')->searchable(),
                TextColumn::make('deliveryAgent.name')->label('Delivery Agent')->searchable(),
                TextColumn::make('requested_status')
                    ->label('Requested')
                    ->state(fn (ShipmentUpdateRequest $record) => $record->requestedStatusLabel())
                    ->badge()
                    ->color('info'),
                ImageColumn::make('photo_path')->label('Proof Photo')->disk('public')->square(),
                TextColumn::make('note')->limit(40)->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
                static::dateRangeFilter('created_at', 'Submitted'),
            ])
            ->recordActions([
                ViewAction::make()->schema([
                    Section::make('Delivery Update Request')->schema([
                        TextEntry::make('shipment.order.order_number')->label('Order'),
                        TextEntry::make('deliveryAgent.name')->label('Delivery Agent'),
                        TextEntry::make('requested_status')->label('Requested Status')->state(fn (ShipmentUpdateRequest $record) => $record->requestedStatusLabel())->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('note')->placeholder('—')->columnSpanFull(),
                        ImageEntry::make('photo_path')->label('Proof photo')->disk('public')->visible(fn (ShipmentUpdateRequest $record) => filled($record->photo_path))->columnSpanFull(),
                        TextEntry::make('reviewedBy.name')->label('Reviewed by')->placeholder('—'),
                        TextEntry::make('reviewed_at')->dateTime()->placeholder('—'),
                    ])->columns(2),
                ]),
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (ShipmentUpdateRequest $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->modalDescription('This applies the requested status directly to the shipment, which is what the customer sees on their order.')
                    ->action(function (ShipmentUpdateRequest $record) {
                        $shipment = $record->shipment;

                        $payload = ['status' => $record->requested_status];

                        if ($record->requested_status === 'delivered') {
                            $payload['delivered_at'] = $shipment->delivered_at ?? now();
                            if (filled($record->note)) {
                                $payload['proof_of_delivery_note'] = $record->note;
                            }
                            if (filled($record->photo_path)) {
                                $payload['proof_photo_path'] = $record->photo_path;
                            }
                        } else {
                            $payload['dispatched_at'] = $shipment->dispatched_at ?? now();
                            if (filled($record->note)) {
                                $payload['notes'] = trim(($shipment->notes ? $shipment->notes."\n" : '').$record->note);
                            }
                        }

                        $shipment->update($payload);

                        $record->update([
                            'status' => 'approved',
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);

                        ActivityLog::log(
                            'delivery_update_approved',
                            "Approved {$record->deliveryAgent->name}'s update on order {$shipment->order->order_number}: {$record->requestedStatusLabel()}.",
                            $shipment,
                            Auth::user(),
                        );

                        Notification::make()->title('Update approved and applied to the shipment')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (ShipmentUpdateRequest $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')->label('Reason (optional, not shown to the agent yet)'),
                    ])
                    ->action(function (ShipmentUpdateRequest $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'note' => trim($record->note."\n\nRejected: ".($data['reason'] ?? '')),
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);

                        ActivityLog::log(
                            'delivery_update_rejected',
                            "Rejected {$record->deliveryAgent->name}'s update on order {$record->shipment->order->order_number}: {$record->requestedStatusLabel()}.",
                            $record->shipment,
                            Auth::user(),
                        );

                        Notification::make()->title('Update rejected')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDeliveryUpdateRequests::route('/'),
        ];
    }
}
