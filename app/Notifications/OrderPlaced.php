<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlaced extends Notification
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order confirmed: {$this->order->order_number}")
            ->greeting("Thanks for your order, {$notifiable->name}!")
            ->line("Your order {$this->order->order_number} has been placed and is now pending confirmation.")
            ->line('Total: '.number_format($this->order->total).' '.$this->order->currency)
            ->action('View your order', route('orders.show', $this->order))
            ->line("We'll email you as soon as its status changes.");
    }
}
