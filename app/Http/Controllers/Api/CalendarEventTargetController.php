<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEventTarget;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarEventTargetController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:calendar_event_targets.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:calendar_event_targets.create')->only(['store']);
        $this->middleware('admin_or_permission:calendar_event_targets.update')->only(['update']);
        $this->middleware('admin_or_permission:calendar_event_targets.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeysExact' => ['calendar_event_id', 'target_type', 'target_id'],
            'eagerLoads' => ['calendarEvent'],
        ];

        return $this->commonIndex($request, CalendarEventTarget::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'calendar_event_id' => 'required|exists:calendar_events,id',
            'target_type' => 'required|string|max:255',
            'target_id' => 'required|integer',
        ]);

        return $this->commonStore($request, CalendarEventTarget::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $target = CalendarEventTarget::with(['calendarEvent'])->findOrFail($id);

        return $this->jsonResponseOk($target);
    }

    public function update(Request $request, CalendarEventTarget $calendarEventTarget): JsonResponse
    {
        $request->validate([
            'calendar_event_id' => 'sometimes|required|exists:calendar_events,id',
            'target_type' => 'sometimes|required|string|max:255',
            'target_id' => 'sometimes|required|integer',
        ]);

        return $this->commonUpdate($request, $calendarEventTarget);
    }

    public function destroy(CalendarEventTarget $calendarEventTarget): JsonResponse
    {
        return $this->commonDestroy($calendarEventTarget);
    }
}
