<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarUser;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarUserController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:calendar_users.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:calendar_users.create')->only(['store']);
        $this->middleware('admin_or_permission:calendar_users.update')->only(['update']);
        $this->middleware('admin_or_permission:calendar_users.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeysExact' => ['calendar_id', 'user_id', 'is_visible'],
            'eagerLoads' => ['calendar', 'user'],
        ];

        return $this->commonIndex($request, CalendarUser::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'calendar_id' => 'required|exists:calendars,id',
            'user_id' => 'required|exists:users,id',
            'is_visible' => 'boolean',
        ]);

        return $this->commonStore($request, CalendarUser::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $calendarUser = CalendarUser::with(['calendar', 'user'])->findOrFail($id);

        return $this->jsonResponseOk($calendarUser);
    }

    public function update(Request $request, CalendarUser $calendarUser): JsonResponse
    {
        $request->validate([
            'calendar_id' => 'sometimes|required|exists:calendars,id',
            'user_id' => 'sometimes|required|exists:users,id',
            'is_visible' => 'boolean',
        ]);

        return $this->commonUpdate($request, $calendarUser);
    }

    public function destroy(CalendarUser $calendarUser): JsonResponse
    {
        return $this->commonDestroy($calendarUser);
    }
}
