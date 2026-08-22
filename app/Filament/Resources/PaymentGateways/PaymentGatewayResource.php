<?php

namespace App\Filament\Resources\PaymentGateways;

use App\Filament\Resources\PaymentGateways\Pages\ManagePaymentGateways;
use App\Models\PaymentGateway;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PaymentGatewayResource extends Resource
{
    protected static ?string $model = PaymentGateway::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Payment Gateways';

    protected static ?string $modelLabel = 'payment gateway';

    protected static ?string $pluralModelLabel = 'payment gateways';

    protected static ?string $recordTitleAttribute = 'display_name';

    // Exactly three fixed provider rows exist (seeded once) - admins
    // configure and toggle them, but never add or remove a provider row,
    // since checkout-time code (once built) will look these up by a fixed
    // 'provider' key.
    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(function (Schema $schema): array {
            $provider = $schema->getRecord()?->provider;

            return [
                Placeholder::make('webhook_url')
                    ->label('Webhook URL')
                    ->content($provider ? route('payments.webhook', ['provider' => $provider]) : '—')
                    ->helperText('Paste this into ' . ucfirst((string) $provider) . "'s dashboard as the webhook/notification URL."),
                Toggle::make('is_enabled')
                    ->label('Enabled')
                    ->helperText('Only enabled gateways will be offered at checkout.'),
                Select::make('mode')
                    ->options(['test' => 'Test / Sandbox', 'live' => 'Live'])
                    ->required(),
                ...match ($provider) {
                    'flutterwave' => [
                        TextInput::make('credentials.public_key')->label('Public Key'),
                        TextInput::make('credentials.secret_key')->label('Secret Key')->password()->revealable(),
                        TextInput::make('credentials.encryption_key')->label('Encryption Key')->password()->revealable(),
                        TextInput::make('credentials.webhook_hash')
                            ->label('Webhook Secret Hash')
                            ->password()
                            ->revealable()
                            ->helperText('Set this same value as the "Secret Hash" in your Flutterwave dashboard under Settings → Webhooks - it\'s how incoming webhooks are verified as genuine.'),
                    ],
                    'paystack' => [
                        TextInput::make('credentials.public_key')->label('Public Key'),
                        TextInput::make('credentials.secret_key')->label('Secret Key')->password()->revealable(),
                    ],
                    'fapshi' => [
                        TextInput::make('credentials.api_user')->label('API User'),
                        TextInput::make('credentials.api_key')->label('API Key')->password()->revealable(),
                    ],
                    default => [],
                },
            ];
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->label('Provider')->sortable(),
                IconColumn::make('is_enabled')->label('Enabled')->boolean(),
                BadgeColumn::make('mode')
                    ->colors([
                        'gray' => 'test',
                        'success' => 'live',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentGateways::route('/'),
        ];
    }
}
