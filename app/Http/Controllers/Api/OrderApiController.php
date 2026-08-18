<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Payments\PaymentGatewayResolver;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class OrderApiController extends Controller
{
    public function createOrder(Request $request, PaymentService $paymentService, PaymentGatewayResolver $resolver)
    {
        $apiKey = $request->header('X-Store-Api-Key');

        if (! $apiKey) {
            return response()->json(['error' => 'API Key requerida'], 400);
        }

        $store = Store::where('api_key', $apiKey)->first();

        if (! $store) {
            return response()->json(['error' => 'Tienda no válida'], 404);
        }

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:50',
            'shipping_method' => 'required|in:pickup,delivery',
            'shipping_address' => 'required_if:shipping_method,delivery|nullable|string|max:500',
            'shipping_notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:99',
        ]);

        $products = $store->products()
            ->where('is_active', true)
            ->whereIn('id', collect($request->items)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $lineItems = [];
        $subtotal = 0;

        foreach ($request->items as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                return response()->json(['error' => 'Producto no válido para esta tienda'], 422);
            }

            $quantity = (int) $item['quantity'];
            $unitPrice = $product->offer_price ?? $product->price;
            $lineTotal = round((float) $unitPrice * $quantity, 2);

            $lineItems[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];

            $subtotal += $lineTotal;
        }

        $total = round($subtotal, 2);

        $provider = $resolver->activeProviderFor($store);

        // Flujo con pago: el pedido queda pendiente y se redirige al checkout
        if ($provider) {
            $order = $store->orders()->create([
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'shipping_method' => $request->shipping_method,
                'shipping_address' => $request->shipping_address,
                'shipping_notes' => $request->shipping_notes,
                'subtotal' => $subtotal,
                'total' => $total,
                'status' => 'pending',
                'payment_status' => 'pending',
            ]);

            $order->items()->createMany($lineItems);

            $checkoutUrl = $paymentService->startPayment(
                $store,
                $order,
                $total,
                $store->currency,
                'Pedido #'.$order->id.' - '.$request->customer_name,
                $provider,
            );

            if (! $checkoutUrl) {
                $order->update(['status' => 'cancelled']);

                return response()->json(['error' => 'No se pudo iniciar el pago. Inténtalo de nuevo.'], 500);
            }

            return response()->json([
                'message' => 'Redirigiendo al pago',
                'order_id' => $order->id,
                'checkout_url' => $checkoutUrl,
            ], 201);
        }

        // Flujo sin pago: confirmación inmediata
        $order = $store->orders()->create([
            'customer_name' => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'shipping_method' => $request->shipping_method,
            'shipping_address' => $request->shipping_address,
            'shipping_notes' => $request->shipping_notes,
            'subtotal' => $subtotal,
            'total' => $total,
            'status' => 'confirmed',
            'payment_status' => 'free',
        ]);

        $order->items()->createMany($lineItems);

        $paymentService->sendOrderEmails($store, $order->load('items'));

        return response()->json([
            'message' => 'Pedido recibido con éxito',
            'order_id' => $order->id,
        ], 201);
    }
}
