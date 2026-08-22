<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Notifications\AdminBroadcastEmail;
use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use UnitEnum;

class SendEmail extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.send-email';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Send Email';

    public ?array $data = [];

    // Emailing the entire user base (or impersonating a "from Selbuildi"
    // voice to any one user) is powerful enough that it's restricted to
    // super admins, same tier as managing admin accounts.
    public static function canAccess(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['recipients' => 'all']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Radio::make('recipients')
                    ->label('Send to')
                    ->options([
                        'all' => 'All users',
                        'specific' => 'A specific user',
                    ])
                    ->default('all')
                    ->live()
                    ->required(),
                Select::make('user_id')
                    ->label('User')
                    ->options(fn () => User::orderBy('name')->pluck('email', 'id'))
                    ->searchable()
                    ->required()
                    ->visible(fn ($get) => $get('recipients') === 'specific'),
                TextInput::make('subject')
                    ->required()
                    ->maxLength(255),
                Textarea::make('body')
                    ->label('Message')
                    ->required()
                    ->rows(8)
                    ->helperText('Each line becomes its own paragraph in the email. Sent using the same branded template as every other Selbuildi email.'),
            ]);
    }

    public function send(): void
    {
        $data = $this->form->getState();

        $recipients = $data['recipients'] === 'all'
            ? User::query()
            : User::where('id', $data['user_id']);

        $count = 0;

        $recipients->chunk(200, function ($users) use ($data, &$count) {
            Notification::send($users, new AdminBroadcastEmail($data['subject'], $data['body']));
            $count += $users->count();
        });

        $this->form->fill(['recipients' => 'all']);

        FilamentNotification::make()
            ->title("Email sent to {$count} ".str($count === 1 ? 'user' : 'users').'.')
            ->success()
            ->send();
    }
}
