<?php

namespace App\Filament\Resources\CreditTierSettings;

use App\Filament\Resources\CreditTierSettings\Pages\ManageCreditTierSettings;
use App\Models\CreditTierSetting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CreditTierSettingResource extends Resource
{
    protected static ?string $model = CreditTierSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static UnitEnum|string|null $navigationGroup = 'Trust & Credit';

    protected static ?string $navigationLabel = 'Tier Settings';

    protected static ?string $recordTitleAttribute = 'tier';

    // Fixed set of four tiers, seeded by migration - no legitimate "admin
    // adds a fifth tier" workflow, so create/delete are deliberately not
    // offered anywhere on this resource.
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('perk_headline')
                    ->label('What customers see on the homepage')
                    ->required()
                    ->maxLength(255),
                TextInput::make('auto_approve_limit')
                    ->label('Auto-approve credit limit (XAF)')
                    ->numeric()
                    ->helperText('Leave blank if this tier should never auto-approve - applications still go to manual review.'),
                TextInput::make('net_terms_days')
                    ->label('Net payment terms (days)')
                    ->numeric()
                    ->helperText('How many days after an order to repay a credit drawdown. Leave blank to fall back to 15 days.'),
                TextInput::make('deposit_percentage')
                    ->label('Deposit percentage')
                    ->numeric()
                    ->suffix('%')
                    ->helperText('Display only for now - not yet enforced at checkout.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tier')->label('Tier')->formatStateUsing(fn (CreditTierSetting $record) => $record->tierLabel())->badge(),
                TextColumn::make('perk_headline')->label('Homepage copy'),
                TextColumn::make('auto_approve_limit')->label('Auto-approve limit')->numeric()->placeholder('Manual review only')->suffix(' XAF'),
                TextColumn::make('net_terms_days')->label('Net terms')->suffix(' days')->placeholder('—'),
                TextColumn::make('deposit_percentage')->label('Deposit')->suffix('%')->placeholder('—'),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCreditTierSettings::route('/'),
        ];
    }
}
