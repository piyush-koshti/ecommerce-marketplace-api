<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $inventoryItemId,
        public readonly string $sellerSku,
        public readonly int $availableQuantity,
        public readonly int $threshold,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'inventory.low_stock',
            'inventory_item_id' => $this->inventoryItemId,
            'seller_sku' => $this->sellerSku,
            'available_quantity' => $this->availableQuantity,
            'low_stock_threshold' => $this->threshold,
            'message' => "Low stock for SKU {$this->sellerSku}.",
        ];
    }
}
