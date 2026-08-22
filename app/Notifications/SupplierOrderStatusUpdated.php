<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierOrderStatusUpdated extends Notification
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
            ->greeting('Order update')
            ->line("An order containing your products ({$this->order->order_number}) is now: **{$this->order->statusLabel()}**.");

        if ($this->note !== '') {
            $message->line($this->note);
        }

        return $message->action('View your orders', route('supplier.orders.index'));
    }
}
