<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
use App\Services\OrderFulfillmentService;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static UnitEnum|string|null $navigationGroup = 'Commerce';

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            //
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->searchable()->sortable(),
                TextColumn::make('user.name')->label('Customer')->searchable(),
                BadgeColumn::make('status')
                    ->colors([
                        'warning' => ['pending', 'confirmed', 'processing'],
                        'info' => ['shipped', 'out_for_delivery'],
                        'success' => 'delivered',
                        'danger' => ['cancelled', 'refunded'],
                    ]),
                TextColumn::make('total')->numeric()->sortable()->suffix(' XAF'),
                TextColumn::make('payment_method'),
                TextColumn::make('placed_at')->dateTime()->sortable(),
            ])
            ->defaultSort('placed_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(array_combine(Order::STATUSES, array_map('ucfirst', Order::STATUSES))),
            ])
            ->recordActions([
                Action::make('updateStatus')
                    ->label('Update Status')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->schema([
                        Select::make('status')
                            ->options(array_combine(Order::STATUSES, array_map('ucfirst', Order::STATUSES)))
                            ->required(),
                        Textarea::make('note')->label('Note (optional, emailed to the customer)'),
                    ])
                    ->fillForm(fn (Order $record) => ['status' => $record->status])
                    ->action(function (Order $record, array $data, OrderFulfillmentService $fulfillmentService) {
                        $fulfillmentService->advanceOrderStatus($record, $data['status'], $data['note'] ?? null, Auth::user());
                        Notification::make()->title('Order status updated')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrders::route('/'),
        ];
    }
}
