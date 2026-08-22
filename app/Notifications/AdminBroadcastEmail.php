<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminBroadcastEmail extends Notification
{
    use Queueable;

    public function __construct(public string $emailSubject, public string $body)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->emailSubject);

        foreach (preg_split('/\R/', trim($this->body)) as $line) {
            if ($line !== '') {
                $message->line($line);
            }
        }

        return $message;
    }
}
