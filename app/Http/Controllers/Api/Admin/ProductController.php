<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesStoreAccess;
use App\Http\Controllers\Api\Admin\Concerns\HandlesImages;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use AuthorizesStoreAccess, HandlesImages;

    public function index(Request $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $products = $store->products()->with('category')->orderByDesc('id')->get();

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $data = $request->validated();
        unset($data['image']);

        $data['image_path'] = $this->storeImage($request->file('image'), 'products');

        $product = $store->products()->create($data);

        return ProductResource::make($product->load('category'));
    }

    public function show(Request $request, Store $store, Product $product)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $product);

        return ProductResource::make($product->load('category'));
    }

    public function update(UpdateProductRequest $request, Store $store, Product $product)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $product);

        $data = $request->validated();
        unset($data['image']);

        $data['image_path'] = $this->storeImage($request->file('image'), 'products', $product->image_path);

        $product->update($data);

        return ProductResource::make($product->load('category'));
    }

    public function destroy(Request $request, Store $store, Product $product)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $product);

        $this->deleteImage($product->image_path);
        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente.']);
    }
}
