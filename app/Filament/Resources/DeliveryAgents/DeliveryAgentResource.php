<?php

namespace App\Filament\Resources\DeliveryAgents;

use App\Filament\Concerns\HasDateRangeFilter;
use App\Filament\Resources\DeliveryAgents\Pages\ManageDeliveryAgents;
use App\Filament\Resources\Users\UserResource;
use App\Models\DeliveryAgent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
            FileUpload::make('photo_path')
                ->label('Profile Picture')
                ->disk('public')
                ->directory('delivery-agent-photos')
                ->image()
                ->maxSize(2048),
            Toggle::make('is_active')
                ->label('Approved')
                ->helperText('An agent must be approved before they can be assigned to shipments (or, if self-registered, before they can see anything in their own dashboard beyond a pending-approval notice).')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')->label('Photo')->disk('public')->circular(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->label('Phone'),
                TextColumn::make('vehicle_id')->label('Vehicle')->placeholder('—'),
                TextColumn::make('engagement_status')
                    ->label('Status')
                    ->state(fn (DeliveryAgent $record) => match ($record->engagementStatus()) {
                        'not_approved' => 'Not Approved',
                        'engaged' => 'Engaged',
                        default => 'Free',
                    })
                    ->badge()
                    ->color(fn (DeliveryAgent $record) => match ($record->engagementStatus()) {
                        'not_approved' => 'gray',
                        'engaged' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('current_order')
                    ->label('Current Order')
                    ->state(fn (DeliveryAgent $record) => $record->currentShipment()?->order?->order_number)
                    ->placeholder('—'),
                TextColumn::make('shipments_count')->counts('shipments')->label('Total Deliveries'),
                TextColumn::make('created_at')->label('Added')->date()->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Approval')
                    ->trueLabel('Approved')
                    ->falseLabel('Not approved'),
                SelectFilter::make('account')
                    ->label('Account')
                    ->options(['self' => 'Self-registered', 'roster' => 'Admin-added (no login)'])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        'self' => $query->whereNotNull('user_id'),
                        'roster' => $query->whereNull('user_id'),
                        default => $query,
                    }),
                static::dateRangeFilter('created_at', 'Added'),
            ])
            ->recordActions([
                ViewAction::make()->schema([
                    Section::make('Delivery Agent')->schema([
                        ImageEntry::make('photo_path')->label('Photo')->disk('public')->circular(),
                        TextEntry::make('name'),
                        TextEntry::make('email'),
                        TextEntry::make('phone')->label('Phone (primary)'),
                        TextEntry::make('phone_2')->label('Phone (secondary)')->placeholder('—'),
                        TextEntry::make('vehicle_id')->label('Vehicle')->placeholder('—'),
                        TextEntry::make('account')
                            ->label('Account')
                            ->state(fn (DeliveryAgent $record) => $record->user_id ? 'Self-registered (can log in)' : 'Admin-added (no login)'),
                    ])->columns(2),
                    Section::make('Delivery History')
                        ->description('Every shipment this agent has ever been assigned to, most recent first.')
                        ->schema([
                            RepeatableEntry::make('shipments')
                                ->label('')
                                ->schema([
                                    TextEntry::make('order.order_number')->label('Order')->placeholder('—'),
                                    TextEntry::make('status')->badge(),
                                    TextEntry::make('dispatched_at')->dateTime()->placeholder('—'),
                                    TextEntry::make('delivered_at')->dateTime()->placeholder('—'),
                                ])
                                ->columns(4),
                        ]),
                ]),
                Action::make('downloadDocument')
                    ->label('Document')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('gray')
                    ->visible(fn (DeliveryAgent $record) => filled($record->document_path))
                    ->action(fn (DeliveryAgent $record) => Storage::disk('local')->download(
                        $record->document_path,
                        $record->name.'-id.'.pathinfo($record->document_path, PATHINFO_EXTENSION)
                    )),
                Action::make('viewUserProfile')
                    ->label('User Profile')
                    ->icon(Heroicon::OutlinedUser)
                    ->color('gray')
                    ->visible(fn (DeliveryAgent $record) => $record->user_id !== null)
                    ->url(fn (DeliveryAgent $record) => UserResource::getUrl('index').'?tableSearch='.urlencode($record->email))
                    ->openUrlInNewTab(),
                Action::make('toggleActive')
                    ->label(fn (DeliveryAgent $record) => $record->is_active ? 'Revoke Approval' : 'Approve')
                    ->icon(fn (DeliveryAgent $record) => $record->is_active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                    ->color(fn (DeliveryAgent $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (DeliveryAgent $record) {
                        $record->update(['is_active' => ! $record->is_active]);
                        Notification::make()
                            ->title($record->is_active ? 'Agent approved' : 'Agent approval revoked')
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
