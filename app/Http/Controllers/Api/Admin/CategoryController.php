<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesStoreAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use AuthorizesStoreAccess;

    public function index(Request $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $categories = $store->categories()
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $category = $store->categories()->create($request->validated());

        return CategoryResource::make($category);
    }

    public function show(Request $request, Store $store, Category $category)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $category);

        return CategoryResource::make($category);
    }

    public function update(UpdateCategoryRequest $request, Store $store, Category $category)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $category);

        $category->update($request->validated());

        return CategoryResource::make($category);
    }

    public function destroy(Request $request, Store $store, Category $category)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $category);

        $category->delete();

        return response()->json(['message' => 'Categoría eliminada correctamente.']);
    }
}
