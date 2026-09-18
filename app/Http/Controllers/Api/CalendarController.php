<?php

namespace App\Http\Controllers\Api;

use App\Enums\CalendarType;
use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:calendars.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:calendars.create')->only(['store']);
        $this->middleware('admin_or_permission:calendars.update')->only(['update']);
        $this->middleware('admin_or_permission:calendars.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['name'],
            'filterKeysExact' => ['school_id', 'user_id', 'type', 'is_active'],
            'filterDate' => ['created_at', 'updated_at'],
            'eagerLoads' => ['school', 'user'],
        ];

        return $this->commonIndex($request, Calendar::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', array_column(CalendarType::cases(), 'value')),
            'school_id' => 'nullable|exists:schools,id',
            'user_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        return $this->commonStore($request, Calendar::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $calendar = Calendar::with(['school', 'user', 'events'])->findOrFail($id);

        return $this->jsonResponseOk($calendar);
    }

    public function update(Request $request, Calendar $calendar): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:' . implode(',', array_column(CalendarType::cases(), 'value')),
            'school_id' => 'sometimes|nullable|exists:schools,id',
            'user_id' => 'sometimes|nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        return $this->commonUpdate($request, $calendar);
    }

    public function destroy(Calendar $calendar): JsonResponse
    {
        return $this->commonDestroy($calendar);
    }
}
