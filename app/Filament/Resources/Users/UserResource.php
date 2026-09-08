<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\HasDateRangeFilter;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Country;
use App\Models\User;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                Toggle::make('is_active')
                    ->label('Account active')
                    ->helperText('A deactivated account is signed out immediately and can no longer log in.')
                    ->default(true),
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
                IconColumn::make('is_active')->label('Active')->boolean(),
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
                TernaryFilter::make('is_active')
                    ->label('Account status')
                    ->trueLabel('Active')
                    ->falseLabel('Deactivated'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Procurement History')
                    ->icon(Heroicon::OutlinedClock)
                    ->modalHeading(fn (User $record) => "Procurement History — {$record->name}")
                    ->schema([
                        Section::make('Trust Score')
                            ->schema([
                                TextEntry::make('trustScore.score')->label('Score')->default(0),
                                TextEntry::make('trustScore.tier')->label('Tier')->default('unrated')->badge(),
                                TextEntry::make('trustScore.calculated_at')->label('Last calculated')->dateTime()->placeholder('—'),
                            ])
                            ->columns(3),
                        Section::make('Event History')
                            ->description('Every discrete event that has contributed to this score, oldest first.')
                            ->schema([
                                RepeatableEntry::make('trustScoreEvents')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('event_type')->label('Event')->badge(),
                                        TextEntry::make('points_delta')->label('Points'),
                                        TextEntry::make('relatedOrder.order_number')->label('Order')->placeholder('—'),
                                        TextEntry::make('created_at')->label('At')->dateTime('M j, Y g:i:s A'),
                                    ])
                                    ->columns(4),
                            ]),
                    ]),
                Action::make('impersonate')
                    ->label('Log in as')
                    ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                    ->color('gray')
                    ->url(fn (User $record) => route('impersonation.start', $record))
                    ->openUrlInNewTab()
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $record) => "You'll be logged into {$record->name}'s account in a new tab. Your admin session stays active here.")
                    ->visible(fn (User $record) => $record->is_active),
                Action::make('toggleActive')
                    ->label(fn (User $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (User $record) => $record->is_active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                    ->color(fn (User $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->update(['is_active' => ! $record->is_active]);

                        Notification::make()
                            ->title($record->is_active ? 'Account activated' : 'Account deactivated')
                            ->success()
                            ->send();
                    }),
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
