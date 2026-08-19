<?php

namespace App\Filament\Resources\SupplierProfiles;

use App\Filament\Concerns\HasDateRangeFilter;
use App\Filament\Resources\SupplierProfiles\Pages\ManageSupplierProfiles;
use App\Models\SupplierProfile;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SupplierProfileResource extends Resource
{
    use HasDateRangeFilter;

    protected static ?string $model = SupplierProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static UnitEnum|string|null $navigationGroup = 'Verification';

    protected static ?string $navigationLabel = 'Suppliers';

    protected static ?string $recordTitleAttribute = 'business_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('business_name')->required()->maxLength(255),
            TextInput::make('registration_no')->label('Registration Number')->maxLength(255),
            Textarea::make('description')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business_name')->searchable()->sortable(),
                TextColumn::make('user.name')->label('Contact')->searchable(),
                TextColumn::make('user.email')->searchable(),
                TextColumn::make('user.phone')->label('Phone'),
                IconColumn::make('verified_at')->label('Verified')->boolean(),
                TextColumn::make('created_at')->label('Registered')->date()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('verified_at')
                    ->label('Verification status')
                    ->nullable()
                    ->trueLabel('Verified')
                    ->falseLabel('Pending'),
                static::dateRangeFilter('created_at', 'Registered'),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Verify')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (SupplierProfile $record) => ! $record->isVerified())
                    ->requiresConfirmation()
                    ->action(function (SupplierProfile $record) {
                        $record->update(['verified_at' => now()]);
                        Notification::make()->title('Supplier verified')->success()->send();
                    }),
                Action::make('unverify')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (SupplierProfile $record) => $record->isVerified())
                    ->requiresConfirmation()
                    ->action(function (SupplierProfile $record) {
                        $record->update(['verified_at' => null]);
                        Notification::make()->title('Verification revoked')->warning()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSupplierProfiles::route('/'),
        ];
    }
}
