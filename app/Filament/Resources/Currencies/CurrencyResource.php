<?php

namespace App\Filament\Resources\Currencies;

use App\Filament\Resources\Currencies\Pages\ManageCurrencies;
use App\Models\Currency;
use App\Services\ExchangeRateFetcher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $recordTitleAttribute = 'code';

    // XAF is the canonical ledger currency every order's real amounts are
    // stored in - deleting it would break every existing order/report.
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return $record->code === 'XAF' ? Response::deny() : Response::allow();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Currency code')
                    ->helperText('3-letter ISO code, e.g. "NGN" for Naira.')
                    ->required()
                    ->length(3)
                    ->alpha()
                    ->formatStateUsing(fn (?string $state) => $state ? Str::upper($state) : $state)
                    ->dehydrateStateUsing(fn (string $state) => Str::upper($state))
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?Currency $record) => $record?->code === 'XAF'),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('symbol')
                    ->helperText('Shown right before the amount, e.g. "₦" or "$".')
                    ->required()
                    ->maxLength(8),
                TextInput::make('country_label')
                    ->label('Representative country')
                    ->helperText('Just for display in the currency switcher, e.g. "Nigeria" - several countries can share one currency.')
                    ->maxLength(255),
                TextInput::make('rate_to_xaf')
                    ->label('Exchange rate (XAF per 1 unit)')
                    ->helperText('How many XAF equal 1 unit of this currency. E.g. if 1 USD = 605 XAF, enter 605.')
                    ->numeric()
                    ->required()
                    ->minValue(0.000001)
                    ->disabled(fn (?Currency $record) => $record?->code === 'XAF')
                    ->suffixAction(
                        Action::make('fetchLiveRate')
                            ->label('Fetch live rate')
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->visible(fn (?Currency $record) => $record?->code !== 'XAF')
                            ->action(function (Get $get, Set $set) {
                                $code = Str::upper((string) $get('code'));
                                $rate = $code ? app(ExchangeRateFetcher::class)->fetchOne($code) : null;

                                if ($rate === null) {
                                    Notification::make()->title('Could not fetch a live rate right now')->danger()->send();

                                    return;
                                }

                                $set('rate_to_xaf', $rate);
                                $set('rate_source', 'live');
                                $set('rate_fetched_at', now()->toDateTimeString());

                                Notification::make()->title("Pulled live rate for {$code}: {$rate} XAF")->body('Review it, then hit Save to apply it.')->success()->send();
                            })
                    ),
                Toggle::make('is_supported')
                    ->label('Available for customers to select')
                    ->helperText('Shown in the currency switcher and selectable at checkout. XAF is always available.')
                    ->default(true)
                    ->disabled(fn (?Currency $record) => $record?->code === 'XAF'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->badge()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('symbol'),
                TextColumn::make('rate_to_xaf')->label('1 unit =')->numeric(decimalPlaces: 2)->suffix(' XAF')->sortable(),
                TextColumn::make('rate_source')->label('Source')->badge()->colors(['gray' => 'manual', 'success' => 'live']),
                TextColumn::make('rate_fetched_at')->label('Last fetched')->dateTime()->placeholder('—'),
                IconColumn::make('is_supported')->label('Available')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Action::make('refreshAllLiveRates')
                    ->label('Refresh All Live Rates')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Pulls current rates and applies them immediately to every currency except XAF. Review the list afterward - you can still edit any rate by hand.')
                    ->action(function () {
                        $rates = app(ExchangeRateFetcher::class)->fetch();

                        if ($rates === null) {
                            Notification::make()->title('Could not reach the exchange rate service')->danger()->send();

                            return;
                        }

                        $updated = 0;

                        foreach (Currency::where('code', '!=', 'XAF')->get() as $currency) {
                            if (! isset($rates[$currency->code])) {
                                continue;
                            }

                            $currency->update([
                                'rate_to_xaf' => $rates[$currency->code],
                                'rate_source' => 'live',
                                'rate_fetched_at' => now(),
                            ]);
                            $updated++;
                        }

                        Notification::make()->title("Refreshed {$updated} currencies")->success()->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCurrencies::route('/'),
        ];
    }
}
