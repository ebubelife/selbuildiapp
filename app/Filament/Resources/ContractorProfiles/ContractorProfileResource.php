<?php

namespace App\Filament\Resources\ContractorProfiles;

use App\Filament\Concerns\HasDateRangeFilter;
use App\Filament\Resources\ContractorProfiles\Pages\ManageContractorProfiles;
use App\Models\ContractorProfile;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ContractorProfileResource extends Resource
{
    use HasDateRangeFilter;

    protected static ?string $model = ContractorProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static UnitEnum|string|null $navigationGroup = 'Verification';

    protected static ?string $navigationLabel = 'Contractors';

    protected static ?string $recordTitleAttribute = 'business_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('business_name')->required()->maxLength(255),
            TextInput::make('business_address')->maxLength(255),
            TextInput::make('specialization')->maxLength(255),
            TextInput::make('years_experience')->numeric(),
            TextInput::make('registration_no')->label('Registration Number')->maxLength(255),
            TextInput::make('license_no')->label('License Number')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')->label('Photo')->disk('public')->circular(),
                TextColumn::make('business_name')->searchable()->sortable(),
                TextColumn::make('user.name')->label('Contact')->searchable(),
                TextColumn::make('user.email')->searchable(),
                TextColumn::make('specialization'),
                TextColumn::make('years_experience')->label('Yrs Exp'),
                BadgeColumn::make('verification_status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'verified',
                        'danger' => 'rejected',
                    ]),
                TextColumn::make('created_at')->label('Applied')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('verification_status')->options([
                    'pending' => 'Pending',
                    'verified' => 'Verified',
                    'rejected' => 'Rejected',
                ]),
                static::dateRangeFilter('created_at', 'Applied'),
            ])
            ->recordActions([
                Action::make('downloadId')
                    ->label('ID Document')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('gray')
                    ->visible(fn (ContractorProfile $record) => filled($record->id_document_path))
                    ->action(fn (ContractorProfile $record) => Storage::disk('local')->download(
                        $record->id_document_path,
                        $record->business_name.'-id.'.pathinfo($record->id_document_path, PATHINFO_EXTENSION)
                    )),
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (ContractorProfile $record) => $record->verification_status !== 'verified')
                    ->requiresConfirmation()
                    ->action(function (ContractorProfile $record) {
                        $record->update(['verification_status' => 'verified', 'verified_at' => now()]);
                        Notification::make()->title('Contractor approved')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (ContractorProfile $record) => $record->verification_status !== 'rejected')
                    ->requiresConfirmation()
                    ->action(function (ContractorProfile $record) {
                        $record->update(['verification_status' => 'rejected', 'verified_at' => null]);
                        Notification::make()->title('Contractor rejected')->warning()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContractorProfiles::route('/'),
        ];
    }
}
