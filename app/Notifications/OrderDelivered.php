<?php

namespace App\Notifications;

use App\Models\ProductOrder;

class OrderDelivered extends HubNotification
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
        return '📬 Order delivered';
    }

    public function body(): string
    {
        return "{$this->order->quantity} x {$this->order->item_name} has arrived.";
    }

    public function url(): string
    {
        return '/orders';
    }
}
