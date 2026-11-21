<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $orderId,
        public readonly int $sellerOrderId,
        public readonly string $fromStatus,
        public readonly string $toStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'order.seller_status_changed',
            'order_id' => $this->orderId,
            'seller_order_id' => $this->sellerOrderId,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'message' => "Your seller order status changed to {$this->toStatus}.",
        ];
    }
}
