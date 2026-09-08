<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketReplied extends Notification
{
    use Queueable;

    public function __construct(public SupportTicket $ticket)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Re: {$this->ticket->subject}")
            ->greeting("Hi {$notifiable->name},")
            ->line("You reached out to us about: \"{$this->ticket->subject}\"")
            ->line('Here is our response:')
            ->line($this->ticket->response)
            ->line('If this doesn\'t fully answer your question, just reply to this email or reach out again from your account.');
    }
}
