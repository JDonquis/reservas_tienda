<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesStoreAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use AuthorizesStoreAccess;

    public function index(Request $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $orders = $store->orders()
            ->with('items')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('date'), fn ($q, $date) => $q->whereDate('created_at', $date))
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Store $store, Order $order)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $order);

        return OrderResource::make($order->load('items'));
    }

    public function cancel(Request $request, Store $store, Order $order)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $order);

        if ($order->status === 'cancelled') {
            return response()->json(['message' => 'El pedido ya se encuentra cancelado.'], 200);
        }

        $order->update(['status' => 'cancelled']);

        return OrderResource::make($order->load('items'));
    }
}
