<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesStoreAccess;
use App\Http\Controllers\Api\Admin\Concerns\HandlesImages;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\Store;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use AuthorizesStoreAccess, HandlesImages;

    public function index(Request $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $services = $store->services()->with('category')->orderByDesc('id')->get();

        return ServiceResource::collection($services);
    }

    public function store(StoreServiceRequest $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $data = $request->validated();
        unset($data['image']);

        $data['image_path'] = $this->storeImage($request->file('image'), 'services');

        $service = $store->services()->create($data);

        return ServiceResource::make($service->load('category'));
    }

    public function show(Request $request, Store $store, Service $service)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $service);

        return ServiceResource::make($service->load('category'));
    }

    public function update(UpdateServiceRequest $request, Store $store, Service $service)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $service);

        $data = $request->validated();
        unset($data['image']);

        $data['image_path'] = $this->storeImage($request->file('image'), 'services', $service->image_path);

        $service->update($data);

        return ServiceResource::make($service->load('category'));
    }

    public function destroy(Request $request, Store $store, Service $service)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $service);

        $this->deleteImage($service->image_path);
        $service->delete();

        return response()->json(['message' => 'Servicio eliminado correctamente.']);
    }
}
