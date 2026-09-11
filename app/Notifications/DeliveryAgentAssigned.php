<?php

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliveryAgentAssigned extends Notification
{
    use Queueable;

    public function __construct(public Shipment $shipment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->shipment->order;

        return (new MailMessage)
            ->subject("New delivery assigned - Order {$order->order_number}")
            ->greeting("Hi {$notifiable->name},")
            ->line("You've been assigned to deliver order **{$order->order_number}**.")
            ->line('Supplier: '.($this->shipment->supplierProfile?->business_name ?? '—'))
            ->line('Log in to your dashboard to see the details and update its status once you pick it up.')
            ->action('View my deliveries', route('deliveries.index'));
    }
}
