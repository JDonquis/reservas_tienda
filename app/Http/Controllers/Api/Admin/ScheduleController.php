<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesStoreAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Models\Store;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    use AuthorizesStoreAccess;

    public function index(Request $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $schedules = $store->schedules()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return ScheduleResource::collection($schedules);
    }

    public function store(StoreScheduleRequest $request, Store $store)
    {
        $this->authorizeStore($request->user(), $store);

        $schedule = $store->schedules()->create($request->validated());

        return ScheduleResource::make($schedule);
    }

    public function show(Request $request, Store $store, Schedule $schedule)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $schedule);

        return ScheduleResource::make($schedule);
    }

    public function update(UpdateScheduleRequest $request, Store $store, Schedule $schedule)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $schedule);

        $schedule->update($request->validated());

        return ScheduleResource::make($schedule);
    }

    public function destroy(Request $request, Store $store, Schedule $schedule)
    {
        $this->authorizeStore($request->user(), $store);
        $this->ensureBelongsToStore($store, $schedule);

        $schedule->delete();

        return response()->json(['message' => 'Horario eliminado correctamente.']);
    }
}
