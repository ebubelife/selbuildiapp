<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use UnitEnum;

class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Site Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'whatsapp_number' => SiteSetting::get('whatsapp_number'),
            'whatsapp_message' => SiteSetting::get('whatsapp_message'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('whatsapp_number')
                    ->label('Business WhatsApp number')
                    ->helperText('Full international number - country code included, e.g. 237670000000. Spaces, "+" and dashes are fine, they get stripped. Leave blank to hide the WhatsApp button.')
                    ->maxLength(30),
                TextInput::make('whatsapp_message')
                    ->label('Pre-filled message (optional)')
                    ->helperText('Text that appears already typed when a visitor opens the chat, e.g. "Hi Selbuildi, I have a question about".')
                    ->maxLength(255),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Store digits only - wa.me needs a bare international number.
        $number = preg_replace('/\D+/', '', (string) ($data['whatsapp_number'] ?? ''));

        SiteSetting::set('whatsapp_number', $number ?: null);
        SiteSetting::set('whatsapp_message', Str::of($data['whatsapp_message'] ?? '')->trim()->value() ?: null);

        $this->form->fill([
            'whatsapp_number' => $number ?: null,
            'whatsapp_message' => $data['whatsapp_message'] ?? null,
        ]);

        Notification::make()->title('Settings saved')->success()->send();
    }
}
