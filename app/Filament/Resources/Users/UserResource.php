<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\HasDateRangeFilter;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Country;
use App\Models\User;
use BackedEnum;
use UnitEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    use HasDateRangeFilter;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static UnitEnum|string|null $navigationGroup = 'Verification';

    // Admins and super admins are managed separately in AdminResource -
    // they're staff accounts, not marketplace participants, and shouldn't
    // be mixed into (or promotable from) this list.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotIn('role', ['admin', 'super_admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->maxLength(255),
                TextInput::make('phone')->maxLength(30),
                Select::make('role')
                    ->options([
                        'customer' => 'Customer',
                        'contractor' => 'Contractor',
                        'supplier' => 'Supplier',
                    ])
                    ->required(),
                Select::make('country')
                    ->label('Country of Residence')
                    ->options(fn () => Country::orderBy('name')->pluck('name', 'name'))
                    ->searchable(),
                Select::make('project_country')
                    ->label('Project Country')
                    ->options(fn () => Country::orderBy('name')->pluck('name', 'name'))
                    ->searchable(),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->helperText('Leave blank to keep the current password when editing.')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                    ->dehydrated(fn (?string $state) => filled($state)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                BadgeColumn::make('role')
                    ->colors([
                        'gray' => 'customer',
                        'info' => 'contractor',
                        'warning' => 'supplier',
                    ]),
                TextColumn::make('trustScore.tier')->label('Trust Tier')->default('unrated'),
                TextColumn::make('created_at')->label('Joined')->date()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')->options([
                    'customer' => 'Customer',
                    'contractor' => 'Contractor',
                    'supplier' => 'Supplier',
                ]),
                static::dateRangeFilter('created_at', 'Joined'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
