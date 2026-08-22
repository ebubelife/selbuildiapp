<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeEmail extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return match ($notifiable->role) {
            'supplier' => (new MailMessage)
                ->subject('Welcome to Selbuildi - your supplier account is set up')
                ->greeting("Welcome, {$notifiable->name}!")
                ->line('Your supplier account on Selbuildi has been created.')
                ->line('Our team will review your business details next - once verified, you can start listing products and reaching buyers across Cameroon and the diaspora.')
                ->action('Go to your dashboard', route('dashboard')),

            'contractor' => (new MailMessage)
                ->subject('Welcome to Selbuildi - verification in progress')
                ->greeting("Welcome, {$notifiable->name}!")
                ->line('Your contractor account on Selbuildi has been created.')
                ->line("We're reviewing the documents you submitted - you'll be notified as soon as your account is verified.")
                ->line('Once verified, every purchase you make starts building your Procurement Trust Score.')
                ->action('Go to your dashboard', route('dashboard')),

            default => (new MailMessage)
                ->subject('Welcome to Selbuildi')
                ->greeting("Welcome, {$notifiable->name}!")
                ->line('Your Selbuildi account is ready. Buy building materials from verified suppliers, track every delivery, and build a purchasing history that can unlock credit over time.')
                ->action('Start shopping', route('shop.index')),
        };
    }
}
