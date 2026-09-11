<?php

namespace App\Filament\Resources\SupportTickets;

use App\Filament\Concerns\HasDateRangeFilter;
use App\Filament\Resources\SupportTickets\Pages\ManageSupportTickets;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketReplied;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

class SupportTicketResource extends Resource
{
    use HasDateRangeFilter;

    protected static ?string $model = SupportTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static UnitEnum|string|null $navigationGroup = 'Verification';

    protected static ?string $navigationLabel = 'Enquiries';

    protected static ?string $recordTitleAttribute = 'subject';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        // No create/edit form - tickets only ever arrive from the customer
        // side (or the "Can't Find What You Need"/quote forms); admins act
        // on them entirely through the record actions below.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('From')->searchable(),
                BadgeColumn::make('user.role')
                    ->label('Account')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—')
                    ->colors([
                        'gray' => 'customer',
                        'info' => 'contractor',
                        'warning' => 'supplier',
                    ]),
                BadgeColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn (SupportTicket $record) => $record->typeLabel())
                    ->colors([
                        'gray' => 'general',
                        'info' => 'procurement_request',
                        'warning' => 'quote_request',
                        'danger' => 'complaint',
                    ]),
                TextColumn::make('subject')->limit(40)->searchable(),
                TextColumn::make('created_at')->label('Date')->dateTime('M j, Y g:i A')->sortable(),
                BadgeColumn::make('status')
                    ->colors([
                        'danger' => 'new',
                        'warning' => 'in_progress',
                        'info' => 'responded',
                        'success' => 'resolved',
                    ]),
                TextColumn::make('assignedTo.name')->label('Assigned to')->placeholder('Unassigned'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'new' => 'New',
                    'in_progress' => 'In Progress',
                    'responded' => 'Responded',
                    'resolved' => 'Resolved',
                ]),
                SelectFilter::make('account_type')
                    ->label('Account type')
                    ->options(['customer' => 'Customer', 'contractor' => 'Contractor', 'supplier' => 'Supplier'])
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($query, $role) => $query->whereHas('user', fn ($q) => $q->where('role', $role))
                    )),
                SelectFilter::make('type')->options([
                    'general' => 'General Enquiry',
                    'procurement_request' => "Can't Find What You Need",
                    'quote_request' => 'Request a Quote',
                    'complaint' => 'Complaint',
                    'other' => 'Other',
                ]),
                static::dateRangeFilter('created_at', 'Date'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema([
                        Section::make('Message')
                            ->schema([
                                TextEntry::make('user.name')->label('From'),
                                TextEntry::make('user.role')->label('Account type')->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—')->badge(),
                                TextEntry::make('user.email')->label('Email'),
                                TextEntry::make('product.name')->label('Product')->visible(fn (SupportTicket $record) => $record->product_id !== null),
                                TextEntry::make('subject'),
                                TextEntry::make('body')->label('Message')->columnSpanFull(),
                            ])
                            ->columns(2),
                        Section::make('Response')
                            ->visible(fn (SupportTicket $record) => filled($record->response))
                            ->schema([
                                TextEntry::make('response')->label('')->columnSpanFull(),
                                TextEntry::make('responded_at')->label('Responded')->dateTime(),
                            ]),
                    ]),
                Action::make('assign')
                    ->label('Assign')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->color('gray')
                    ->schema([
                        Select::make('assigned_to')
                            ->label('Team member')
                            ->options(fn () => User::whereIn('role', ['admin', 'super_admin'])->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->fillForm(fn (SupportTicket $record) => ['assigned_to' => $record->assigned_to])
                    ->action(function (SupportTicket $record, array $data) {
                        $record->update([
                            'assigned_to' => $data['assigned_to'],
                            'status' => $record->status === 'new' ? 'in_progress' : $record->status,
                        ]);

                        Notification::make()->title('Ticket assigned')->success()->send();
                    }),
                Action::make('reply')
                    ->label('Reply')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('primary')
                    ->schema([
                        Textarea::make('response')
                            ->label('Your reply')
                            ->rows(6)
                            ->required()
                            ->helperText('Emailed to the customer using the standard Selbuildi template.'),
                    ])
                    ->fillForm(fn (SupportTicket $record) => ['response' => $record->response])
                    ->action(function (SupportTicket $record, array $data) {
                        $record->update([
                            'response' => $data['response'],
                            'responded_at' => now(),
                            'status' => 'responded',
                        ]);

                        try {
                            $record->user->notify(new SupportTicketReplied($record));
                        } catch (Throwable $e) {
                            Log::error('SupportTicketReplied notification failed to send', [
                                'ticket_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        Notification::make()->title('Reply sent')->success()->send();
                    }),
                Action::make('markResolved')
                    ->label('Mark Resolved')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (SupportTicket $record) => $record->status !== 'resolved')
                    ->requiresConfirmation()
                    ->action(fn (SupportTicket $record) => $record->update(['status' => 'resolved'])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSupportTickets::route('/'),
        ];
    }
}
