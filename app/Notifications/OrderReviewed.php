<?php

namespace App\Notifications;

use App\Models\ProductOrder;

class OrderReviewed extends HubNotification
{
    public function __construct(private ProductOrder $order)
    {
    }

    public function category(): string
    {
        return 'orders';
    }

    public function title(): string
    {
        return $this->order->status === 'approved'
            ? '✅ Order approved'
            : '❌ Order declined';
    }

    public function body(): string
    {
        $body = "{$this->order->quantity} x {$this->order->item_name} was {$this->order->status}.";

        if ($this->order->status === 'rejected' && $this->order->rejection_reason) {
            $body .= " Reason: {$this->order->rejection_reason}";
        }

        return $body;
    }

    public function url(): string
    {
        return '/orders';
    }
}
