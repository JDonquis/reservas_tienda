<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $stores = $user->role === 'superadmin'
            ? Store::with('owner')->orderByDesc('id')->get()
            : Store::with('owner')->where('user_id', $user->id)->get();

        return StoreResource::collection($stores);
    }

    public function store(StoreStoreRequest $request)
    {
        $data = $request->validated();

        $store = Store::create([
            'name' => $data['name'],
            'allowed_domain' => $data['allowed_domain'] ?? null,
            'user_id' => $data['user_id'] ?? null,
        ]);

        return StoreResource::make($store->load('owner'));
    }

    public function show(Request $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        return StoreResource::make($store->load('owner'));
    }

    public function update(UpdateStoreRequest $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $store->update($request->validated());

        return StoreResource::make($store->load('owner'));
    }

    public function destroy(Request $request, Store $store)
    {
        $store->delete();

        return response()->json(['message' => 'Tienda eliminada correctamente.']);
    }

    public function regenerateKey(Request $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $store->update(['api_key' => Store::generateApiKey()]);

        return StoreResource::make($store->load('owner'));
    }

    protected function authorizeStore($user, Store $store): void
    {
        if ($user->role !== 'superadmin' && $store->user_id !== $user->id) {
            abort(403, 'No tienes permisos sobre esta tienda.');
        }
    }
}
