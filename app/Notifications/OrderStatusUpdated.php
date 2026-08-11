<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(public Order $order, public string $note = '')
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Order {$this->order->order_number}: {$this->order->statusLabel()}")
            ->greeting("Update on order {$this->order->order_number}")
            ->line("Your order is now: **{$this->order->statusLabel()}**.");

        if ($this->note !== '') {
            $message->line($this->note);
        }

        return $message->action('Track your order', route('orders.show', $this->order));
    }
}
