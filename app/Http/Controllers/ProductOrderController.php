<?php

namespace App\Http\Controllers;

use App\Models\ProductOrder;
use App\Models\User;
use App\Notifications\OrderDelivered;
use App\Notifications\OrderRequested;
use App\Notifications\OrderReviewed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class ProductOrderController extends Controller
{
    public function index(Request $request)
    {
        $canManage = Gate::allows('manage_orders');

        $orders = ProductOrder::with(['requestedBy:id,name', 'approvedBy:id,name'])
            ->when(! $canManage, fn ($q) => $q->where('requested_by', $request->user()->id))
            ->orderByRaw("field(status, 'pending', 'approved', 'ordered', 'delivered', 'rejected')")
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'item_name' => $o->item_name,
                'quantity' => (float) $o->quantity,
                'unit' => $o->unit,
                'category' => $o->category,
                'notes' => $o->notes,
                'status' => $o->status,
                'requested_by' => $o->requestedBy->name,
                'is_mine' => $o->requested_by === $request->user()->id,
                'approved_by' => $o->approvedBy?->name,
                'rejection_reason' => $o->rejection_reason,
                'supplier' => $o->supplier,
                'expected_delivery_date' => $o->expected_delivery_date?->toDateString(),
                'cost' => $o->cost !== null ? (float) $o->cost : null,
                'delivered_at' => $o->delivered_at?->toDateString(),
                'delivery_notes' => $o->delivery_notes,
                'created_at' => $o->created_at->toDateString(),
            ]);

        return Inertia::render('Orders', [
            'orders' => $orders,
            'canManage' => $canManage,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_name' => ['required', 'string', 'max:200'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $order = ProductOrder::create([
            ...$data,
            'requested_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        Notification::send(User::managers()->get(), new OrderRequested($order));

        return back()->with('success', 'Order request submitted.');
    }

    public function cancel(Request $request, ProductOrder $order)
    {
        abort_unless(
            $order->requested_by === $request->user()->id && $order->status === 'pending',
            403,
        );

        $order->delete();

        return back()->with('success', 'Order request cancelled.');
    }

    public function approve(ProductOrder $order, Request $request)
    {
        $order->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $order->requestedBy->notify(new OrderReviewed($order));

        return back()->with('success', 'Order approved.');
    }

    public function reject(Request $request, ProductOrder $order)
    {
        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $order->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        $order->requestedBy->notify(new OrderReviewed($order));

        return back()->with('success', 'Order declined.');
    }

    public function markOrdered(Request $request, ProductOrder $order)
    {
        abort_unless($order->status === 'approved', 422, 'Order must be approved before it can be marked as ordered.');

        $data = $request->validate([
            'supplier' => ['nullable', 'string', 'max:150'],
            'expected_delivery_date' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $order->update([...$data, 'status' => 'ordered', 'ordered_at' => now()]);

        return back()->with('success', 'Order marked as placed.');
    }

    public function markDelivered(Request $request, ProductOrder $order)
    {
        abort_unless(in_array($order->status, ['ordered', 'approved'], true), 422, 'Order has not been placed yet.');

        $data = $request->validate([
            'delivery_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order->update([
            ...$data,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $order->requestedBy->notify(new OrderDelivered($order));

        return back()->with('success', 'Order marked as delivered.');
    }
}
