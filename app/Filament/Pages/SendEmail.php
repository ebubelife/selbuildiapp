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
use Illuminate\Support\Facades\Log;
use UnitEnum;
use Throwable;

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

        $sent = 0;
        $failed = 0;

        // Sent one at a time (rather than the batch Notification::send())
        // so one bad address or transient SMTP error only skips that one
        // recipient, not the rest of the run - and so we get an accurate
        // sent/failed count to report back and log, on a broadcast this
        // size that's worth more than the small speed cost.
        $recipients->chunk(200, function ($users) use ($data, &$sent, &$failed) {
            foreach ($users as $user) {
                try {
                    $user->notify(new AdminBroadcastEmail($data['subject'], $data['body']));
                    $sent++;
                } catch (Throwable $e) {
                    $failed++;
                    Log::error('AdminBroadcastEmail notification failed to send', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info('Admin broadcast email finished', [
            'sent_by' => Auth::guard('admin')->id(),
            'subject' => $data['subject'],
            'sent' => $sent,
            'failed' => $failed,
        ]);

        $this->form->fill(['recipients' => 'all']);

        $title = "Email sent to {$sent} ".str($sent === 1 ? 'user' : 'users').'.';

        if ($failed > 0) {
            $title .= " {$failed} failed - see logs.";
        }

        FilamentNotification::make()
            ->title($title)
            ->color($failed > 0 ? 'warning' : 'success')
            ->send();
    }
}
