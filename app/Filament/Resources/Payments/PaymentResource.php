<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Concerns\HasDateRangeFilter;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use App\Services\OrderFulfillmentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class PaymentResource extends Resource
{
    use HasDateRangeFilter;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static UnitEnum|string|null $navigationGroup = 'Commerce';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')->label('Order')->searchable(),
                TextColumn::make('order.user.name')->label('Customer')->searchable(),
                TextColumn::make('provider')->badge(),
                TextColumn::make('amount')->numeric()->sortable()->suffix(fn (Payment $record) => ' '.$record->currency),
                BadgeColumn::make('status')->colors([
                    'warning' => 'pending',
                    'success' => 'paid',
                    'danger' => ['failed', 'refunded'],
                ]),
                TextColumn::make('reference')->limit(24)->searchable()->copyable(),
                TextColumn::make('paid_at')->dateTime()->sortable()->placeholder('—'),
                TextColumn::make('created_at')->label('Initiated')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                    'failed' => 'Failed',
                    'refunded' => 'Refunded',
                ]),
                SelectFilter::make('provider')->options([
                    'flutterwave' => 'Flutterwave',
                    'paystack' => 'Paystack',
                    'fapshi' => 'Fapshi',
                ]),
                static::dateRangeFilter('created_at', 'Initiated'),
            ])
            ->recordActions([
                ViewAction::make()->schema([
                    Section::make('Payment')->schema([
                        TextEntry::make('order.order_number')->label('Order'),
                        TextEntry::make('order.user.name')->label('Customer'),
                        TextEntry::make('order.user.email')->label('Email'),
                        TextEntry::make('provider')->badge(),
                        TextEntry::make('amount')->numeric()->suffix(fn (Payment $record) => ' '.$record->currency),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('reference')->copyable(),
                        TextEntry::make('paid_at')->dateTime()->placeholder('—'),
                    ])->columns(2),
                ]),
                // Admin-recorded refunds only for this phase - no automated
                // call to the provider's own refund API. Simpler to ship,
                // and matches how disputes are actually resolved in
                // practice right now (manually, outside the platform)
                // before real refund volume justifies the extra provider
                // integration work.
                Action::make('refund')
                    ->label('Record Refund')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (Payment $record) => $record->status === 'paid')
                    ->requiresConfirmation()
                    ->modalDescription('This only records the refund in Selbuildi - it does not call the provider to actually move money back. Process the refund with the provider yourself first.')
                    ->schema([
                        Textarea::make('note')->label('Note (optional, emailed to the customer)'),
                    ])
                    ->action(function (Payment $record, array $data, OrderFulfillmentService $fulfillmentService) {
                        DB::transaction(function () use ($record) {
                            $record->update(['status' => 'refunded']);
                            $record->order->update(['payment_status' => 'refunded']);
                        });

                        $fulfillmentService->advanceOrderStatus(
                            $record->order,
                            'refunded',
                            $data['note'] ?: null,
                            Auth::user(),
                        );

                        Log::info('Payment refund recorded', [
                            'payment_id' => $record->id,
                            'order_id' => $record->order_id,
                            'admin_id' => Auth::id(),
                        ]);

                        Notification::make()->title('Refund recorded')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }
}
