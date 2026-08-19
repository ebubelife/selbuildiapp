<?php

namespace App\Filament\Resources\Countries;

use App\Filament\Resources\Countries\Pages\ManageCountries;
use App\Models\Country;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class CountryResource extends Resource
{
    protected static ?string $model = Country::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('code')
                    ->label('ISO Code')
                    ->helperText(new HtmlString(
                        'The 2-letter country code, e.g. "NG" for Nigeria. Look it up on the '.
                        '<a href="https://www.iso.org/obp/ui/#search/code/" target="_blank" rel="noopener" class="underline">ISO 3166 country code list</a>.'
                    ))
                    ->required()
                    ->length(2)
                    ->alpha()
                    ->formatStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state)
                    ->dehydrateStateUsing(fn (string $state) => strtoupper($state))
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->label('ISO Code')->badge(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCountries::route('/'),
        ];
    }
}
