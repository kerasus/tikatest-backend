<?php

namespace App\Http\Controllers\Api;

use App\Enums\CalendarEventSource;
use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventType;
use App\Enums\CalendarType;
use App\Enums\UserRoleType;
use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\SchoolUser;
use App\Models\TermEnrollment;
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
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'all_day' => 'boolean',

            'type' => 'required|in:' .
                implode(',', array_column(CalendarEventType::cases(), 'value')),

            'status' => 'sometimes|in:' .
                implode(',', array_column(CalendarEventStatus::cases(), 'value')),

            'location' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',

            'source' => 'sometimes|in:' .
                implode(',', array_column(CalendarEventSource::cases(), 'value')),

            'is_recurring' => 'boolean',
            'recurrence_rule' => 'nullable|string',
            'recurrence_until' => 'nullable|date',
            'metadata' => 'nullable|array',

            // Calendar context
            'school_id' => 'sometimes|nullable|exists:schools,id',
            'user_id' => 'sometimes|nullable|exists:users,id',
        ]);

        $authUser = $request->user();

        $schoolId = $data['school_id'] ?? null;
        $requestedUserId = $data['user_id'] ?? null;

        /*
         * The user_id sent by the client is only a context request.
         * It must never be trusted directly.
         */
        $userId = $this->resolveEventUserId(
            $authUser,
            $schoolId,
            $requestedUserId
        );

        $calendarId = $this->resolveCalendarId(
            $schoolId,
            $userId
        );

        /*
         * school_id and user_id are request-level context values.
         * They are not columns of calendar_events.
         */
        unset($data['school_id'], $data['user_id']);

        $data['calendar_id'] = $calendarId;

        $createdModel = CalendarEvent::create($data);

        return $this->show($request, $createdModel->id);
    }

    /**
     * Determine which user, if any, owns the event.
     */
    private function resolveEventUserId(
        $authUser,
        ?int $schoolId,
        ?int $requestedUserId
    ): ?int {
        /*
         * Admin:
         * - Can create a system event
         * - Can create a school event
         * - Can create a personal event
         * - Can create a personal event inside a school
         *
         * But "personal" always means the authenticated admin himself.
         */
        if ($authUser->hasRole(UserRoleType::Admin->value)) {
            if ($requestedUserId !== null && $requestedUserId !== $authUser->id) {
                abort(403, 'You can only create personal events for yourself.');
            }

            return $requestedUserId;
        }

        /*
         * Manager / Teacher / Staff:
         * school_id is mandatory.
         */
        if ($authUser->hasAnyRole([
            UserRoleType::Manager->value,
            UserRoleType::Teacher->value,
            UserRoleType::Staff->value,
        ])) {
            if ($schoolId === null) {
                abort(422, 'school_id is required.');
            }

            /*
             * The authenticated user must belong to the requested school.
             */
            $this->ensureSchoolPersonnel($authUser->id, $schoolId);

            /*
             * If user_id is supplied, it must be the authenticated user.
             */
            if ($requestedUserId !== null && $requestedUserId !== $authUser->id) {
                abort(403, 'You can only create personal events for yourself.');
            }

            return $requestedUserId;
        }

        /*
         * Student:
         * school_id is mandatory.
         * Student events are ALWAYS personal.
         */
        if ($authUser->hasRole(UserRoleType::Student->value)) {
            if ($schoolId === null) {
                abort(422, 'school_id is required.');
            }

            /*
             * Student must have an active enrollment in this school.
             */
            $this->ensureStudentEnrollment($authUser->id, $schoolId);

            /*
             * Student cannot create an event for another user.
             */
            if ($requestedUserId !== null && $requestedUserId !== $authUser->id) {
                abort(403, 'You can only create personal events for yourself.');
            }

            /*
             * Do not trust user_id from request.
             * Student events are always owned by the authenticated student.
             */
            return $authUser->id;
        }

        abort(403, 'You are not allowed to create calendar events.');
    }

    /**
     * Verify that a manager/teacher/staff member belongs to the school.
     */
    private function ensureSchoolPersonnel(int $userId, int $schoolId): void
    {
        $exists = SchoolUser::query()
            ->where('user_id', $userId)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            abort(403, 'You are not a member of this school.');
        }
    }

    /**
     * Verify that a student has an active enrollment in the school.
     */
    private function ensureStudentEnrollment(int $userId, int $schoolId): void
    {
        $exists = TermEnrollment::query()
            ->where('user_id', $userId)
            ->where('school_id', $schoolId)
            ->where(function ($query) {
                $query
                    ->whereNull('left_at')
                    ->orWhere('left_at', '>', now());
            })
            ->exists();

        if (! $exists) {
            abort(403, 'You are not enrolled in this school.');
        }
    }

    /**
     * Resolve the calendar according to school/user context.
     *
     * null school + null user
     *      => global system calendar
     *
     * school + null user
     *      => school calendar
     *
     * null school + user
     *      => personal calendar
     *
     * school + user
     *      => personal calendar inside school
     */
    private function resolveCalendarId(
        ?int $schoolId,
        ?int $userId
    ): int {
        if ($schoolId !== null && $userId !== null) {
            return Calendar::firstOrCreate(
                [
                    'type' => CalendarType::Personal,
                    'school_id' => $schoolId,
                    'user_id' => $userId,
                ],
                [
                    'name' => 'تقویم شخصی',
                    'is_active' => true,
                ]
            )->id;
        }

        if ($schoolId !== null) {
            return Calendar::firstOrCreate(
                [
                    'type' => CalendarType::School,
                    'school_id' => $schoolId,
                    'user_id' => null,
                ],
                [
                    'name' => 'تقویم مدرسه',
                    'is_active' => true,
                ]
            )->id;
        }

        if ($userId !== null) {
            return Calendar::firstOrCreate(
                [
                    'type' => CalendarType::Personal,
                    'school_id' => null,
                    'user_id' => $userId,
                ],
                [
                    'name' => 'تقویم شخصی',
                    'is_active' => true,
                ]
            )->id;
        }

        return Calendar::firstOrCreate(
            [
                'type' => CalendarType::National,
                'school_id' => null,
                'user_id' => null,
            ],
            [
                'name' => 'تقویم کل سیستم',
                'is_active' => true,
            ]
        )->id;
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
            'type' => 'sometimes|required|in:' .
                implode(',', array_column(CalendarEventType::cases(), 'value')),
            'status' => 'sometimes|required|in:' .
                implode(',', array_column(CalendarEventStatus::cases(), 'value')),
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'source' => 'sometimes|required|in:' .
                implode(',', array_column(CalendarEventSource::cases(), 'value')),
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
