<?php

namespace App\Filament\Resources\CreditAccounts;

use App\Filament\Resources\CreditAccounts\Pages\ManageCreditAccounts;
use App\Models\CreditAccount;
use App\Services\CreditService;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CreditAccountResource extends Resource
{
    protected static ?string $model = CreditAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static UnitEnum|string|null $navigationGroup = 'Trust & Credit';

    protected static ?string $navigationLabel = 'Credit Applications';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('credit_limit')->numeric()->disabled(),
            TextInput::make('available_credit')->numeric()->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Customer')->searchable()->sortable(),
                TextColumn::make('user.email')->searchable(),
                TextColumn::make('user.trustScore.tier')->label('Trust Tier')->badge(),
                TextColumn::make('credit_limit')->label('Requested/Limit (XAF)')->numeric()->sortable(),
                TextColumn::make('available_credit')->label('Available (XAF)')->numeric(),
                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => ['rejected', 'suspended'],
                        'gray' => 'none',
                    ]),
                TextColumn::make('created_at')->label('Applied')->date()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'suspended' => 'Suspended',
                ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (CreditAccount $record) => $record->status === 'pending')
                    ->schema([
                        TextInput::make('limit')
                            ->label('Approved credit limit (XAF)')
                            ->numeric()
                            ->required()
                            ->default(fn (CreditAccount $record) => $record->credit_limit),
                    ])
                    ->action(function (CreditAccount $record, array $data, CreditService $creditService) {
                        $creditService->review($record, true, (int) $data['limit'], Auth::user());
                        Notification::make()->title('Credit application approved')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (CreditAccount $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (CreditAccount $record, CreditService $creditService) {
                        $creditService->review($record, false, null, Auth::user());
                        Notification::make()->title('Credit application rejected')->warning()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCreditAccounts::route('/'),
        ];
    }
}
