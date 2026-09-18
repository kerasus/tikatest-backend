<?php

namespace App\Http\Controllers\Api;

use App\Enums\CalendarEventSource;
use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventType;
use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:calendar_events.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:calendar_events.create')->only(['store']);
        $this->middleware('admin_or_permission:calendar_events.update')->only(['update']);
        $this->middleware('admin_or_permission:calendar_events.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['title', 'location', 'color'],
            'filterKeysExact' => ['calendar_id', 'all_day', 'is_recurring', 'type', 'status', 'source'],
            'filterDate' => ['starts_at', 'ends_at', 'created_at', 'updated_at', 'recurrence_until'],
            'eagerLoads' => ['calendar'],
        ];

        return $this->commonIndex($request, CalendarEvent::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'calendar_id' => 'required|exists:calendars,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'all_day' => 'boolean',
            'type' => 'required|in:' . implode(',', array_column(CalendarEventType::cases(), 'value')),
            'status' => 'required|in:' . implode(',', array_column(CalendarEventStatus::cases(), 'value')),
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'source' => 'required|in:' . implode(',', array_column(CalendarEventSource::cases(), 'value')),
            'is_recurring' => 'boolean',
            'recurrence_rule' => 'nullable|string',
            'recurrence_until' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);

        return $this->commonStore($request, CalendarEvent::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $event = CalendarEvent::with(['calendar', 'targets'])->findOrFail($id);

        return $this->jsonResponseOk($event);
    }

    public function update(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'sometimes|required|date',
            'ends_at' => 'sometimes|nullable|date|after_or_equal:starts_at',
            'all_day' => 'boolean',
            'type' => 'sometimes|required|in:' . implode(',', array_column(CalendarEventType::cases(), 'value')),
            'status' => 'sometimes|required|in:' . implode(',', array_column(CalendarEventStatus::cases(), 'value')),
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'source' => 'sometimes|required|in:' . implode(',', array_column(CalendarEventSource::cases(), 'value')),
            'is_recurring' => 'boolean',
            'recurrence_rule' => 'nullable|string',
            'recurrence_until' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);

        return $this->commonUpdate($request, $calendarEvent);
    }

    public function destroy(CalendarEvent $calendarEvent): JsonResponse
    {
        return $this->commonDestroy($calendarEvent);
    }
}
