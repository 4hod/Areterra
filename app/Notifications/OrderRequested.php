<?php

namespace App\Notifications;

use App\Models\ProductOrder;

class OrderRequested extends HubNotification
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
        return '📦 Order request from '.$this->order->requestedBy->name;
    }

    public function body(): string
    {
        return "{$this->order->quantity} x {$this->order->item_name}"
            .($this->order->unit ? " ({$this->order->unit})" : '')
            .' — awaiting approval.';
    }

    public function url(): string
    {
        return '/orders';
    }
}
