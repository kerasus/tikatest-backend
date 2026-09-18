<?php

namespace App\Http\Controllers\Api;

use App\Traits\Filter;
use App\Traits\CommonCRUD;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\SkyroomRoomSchedule;
use App\Http\Controllers\Controller;

class SkyroomRoomScheduleController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:skyroom_room_schedules.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:skyroom_room_schedules.create')->only(['store']);
        $this->middleware('admin_or_permission:skyroom_room_schedules.update')->only(['update']);
        $this->middleware('admin_or_permission:skyroom_room_schedules.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'title'
            ],
            'filterKeysExact' => [
                'skyroom_room_id',
                'day_of_week',
                'held_date',
                'is_active'
            ],
            'eagerLoads' => [
                'room.class'
            ],
        ];

        return $this->commonIndex($request, SkyroomRoomSchedule::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'skyroom_room_id' => 'required|exists:skyroom_rooms,id',
            'title'           => 'required|string|max:255',
            'day_of_week'     => 'nullable|integer|between:0,6',
            'held_date'       => 'nullable|date',
            'start_time'      => 'required|date_format:H:i',
            'end_time'        => 'required|date_format:H:i|after:start_time',
            'is_active'       => 'boolean',
        ]);

        return $this->commonStore($request, SkyroomRoomSchedule::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $schedule = SkyroomRoomSchedule::with(['room.class'])->findOrFail($id);

        return $this->jsonResponseOk($schedule);
    }

    public function update(Request $request, SkyroomRoomSchedule $skyroomRoomSchedule): JsonResponse
    {
        $request->validate([
            'skyroom_room_id' => 'sometimes|required|exists:skyroom_rooms,id',
            'title'           => 'sometimes|required|string|max:255',
            'day_of_week'     => 'nullable|integer|between:0,6',
            'held_date'       => 'nullable|date',
            'start_time'      => 'sometimes|required|date_format:H:i',
            'end_time'        => 'sometimes|required|date_format:H:i|after:start_time',
            'is_active'       => 'boolean',
        ]);

        return $this->commonUpdate($request, $skyroomRoomSchedule);
    }

    public function destroy(Request $request, SkyroomRoomSchedule $skyroomRoomSchedule): JsonResponse
    {
        return $this->commonDestroy($skyroomRoomSchedule);
    }
}
