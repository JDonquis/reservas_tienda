<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreApiController extends Controller
{
    public function getServices(Request $request)
    {
        $apiKey = $request->header('X-Store-Api-Key');

        if (! $apiKey) {
            return response()->json(['error' => 'API Key requerida'], 400);
        }

        $store = Store::where('api_key', $apiKey)->first();

        if (! $store) {
            return response()->json(['error' => 'Tienda no válida'], 404);
        }

        $services = $store->services()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'store_name' => $store->name,
            'services' => $services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => (float) $service->price,
                    'duration_minutes' => (int) $service->duration_minutes,
                    'image_url' => $service->image_path ? asset('storage/'.$service->image_path) : null,
                    'category' => $service->category?->name,
                ];
            }),
        ]);
    }

    public function getCatalog(Request $request)
    {
        $apiKey = $request->header('X-Store-Api-Key');

        if (! $apiKey) {
            return response()->json(['error' => 'API Key requerida'], 400);
        }

        $store = Store::where('api_key', $apiKey)->first();

        if (! $store) {
            return response()->json(['error' => 'Tienda no válida'], 404);
        }

        $categories = $store->categories()
            ->with(['products' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();

        return response()->json([
            'store_name' => $store->name,
            'categories' => $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'products' => $category->products->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'price' => (float) $product->price,
                            'offer_price' => $product->offer_price ? (float) $product->offer_price : null,
                            'image_url' => asset('storage/'.$product->image_path),
                        ];
                    }),
                ];
            }),
        ]);
    }
}
