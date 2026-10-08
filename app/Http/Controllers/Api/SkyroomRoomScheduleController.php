<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleType;
use App\Http\Controllers\Controller;
use App\Models\SkyroomRoom;
use App\Models\SkyroomRoomSchedule;
use App\Models\User;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SkyroomRoomScheduleController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin|manager|staff');
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $this->ensureSkyroomManagementRole($user);

        $config = [
            'filterKeys' => [
                'title',
            ],
            'filterKeysExact' => [
                'skyroom_room_id',
                'day_of_week',
                'held_date',
                'is_active',
            ],
            'eagerLoads' => [
                'room.class.academicLevel.academicField',
            ],
            'returnModelQuery' => true,
        ];

        $result = $this->commonIndex($request, SkyroomRoomSchedule::class, $config);

        if (! $user->hasRole(UserRoleType::Admin->value)) {
            $schoolIds = $this->accessibleSchoolIds($user);

            $result['modelQuery']->whereHas(
                'room.class.academicLevel.academicField',
                fn ($query) => $query->whereIn('school_id', $schoolIds)
            );
        }

        return $result['responseWithAttachedCollection']($result['modelQuery']);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skyroom_room_id' => [
                'required',
                'integer',
                'exists:skyroom_rooms,id',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'day_of_week' => [
                'nullable',
                'integer',
                'between:0,6',
            ],
            'held_date' => [
                'nullable',
                'date',
            ],
            'start_time' => [
                'required',
                'date_format:H:i:s',
            ],
            'end_time' => [
                'required',
                'date_format:H:i:s',
                'after:start_time',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $room = SkyroomRoom::query()
            ->findOrFail((int) $validated['skyroom_room_id']);

        /*
         * جلوگیری از ساخت زمان‌بندی برای اتاق مدرسه‌ای
         * که کاربر به آن دسترسی ندارد.
         */
        $this->authorizeRoomAccess($room);

        $schedule = SkyroomRoomSchedule::query()->create($validated);

        $this->clearRoomCaches($room);

        return $this->jsonResponseOk(
            $schedule->load([
                'room.class.academicLevel.academicField',
            ])
        );
    }

    public function show(Request $request, $id): JsonResponse
    {
        $schedule = SkyroomRoomSchedule::query()
            ->with([
                'room.class.academicLevel.academicField',
            ])
            ->findOrFail($id);

        $room = $schedule->room;

        abort_unless(
            $room instanceof SkyroomRoom,
            422,
            'اتاق مرتبط با زمان‌بندی پیدا نشد.'
        );

        $this->authorizeRoomAccess($room);

        return $this->jsonResponseOk($schedule);
    }

    public function update(
        Request $request,
        SkyroomRoomSchedule $skyroomRoomSchedule
    ): JsonResponse {
        /*
         * ابتدا اتاق فعلی زمان‌بندی را دریافت و دسترسی آن را بررسی می‌کنیم.
         */
        $skyroomRoomSchedule->loadMissing([
            'room.class.academicLevel.academicField',
        ]);

        $oldRoom = $skyroomRoomSchedule->room;

        abort_unless(
            $oldRoom instanceof SkyroomRoom,
            422,
            'اتاق فعلی زمان‌بندی پیدا نشد.'
        );

        $this->authorizeRoomAccess($oldRoom);

        $validated = $request->validate([
            'skyroom_room_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:skyroom_rooms,id',
            ],
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'day_of_week' => [
                'nullable',
                'integer',
                'between:0,6',
            ],
            'held_date' => [
                'nullable',
                'date',
            ],
            'start_time' => [
                'sometimes',
                'required',
                'date_format:H:i:s',
            ],
            'end_time' => [
                'sometimes',
                'required',
                'date_format:H:i:s',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        /*
         * اگر زمان‌بندی به اتاق دیگری منتقل شود،
         * دسترسی اتاق مقصد نیز باید بررسی شود.
         */
        $newRoom = $oldRoom;

        if (
            isset($validated['skyroom_room_id'])
            && (int) $validated['skyroom_room_id'] !== (int) $oldRoom->id
        ) {
            $newRoom = SkyroomRoom::query()
                ->findOrFail((int) $validated['skyroom_room_id']);

            $this->authorizeRoomAccess($newRoom);
        }

        /*
         * بررسی نهایی بازه زمانی در حالت Update جزئی
         */
        $startTime = $validated['start_time']
            ?? $skyroomRoomSchedule->start_time;

        $endTime = $validated['end_time']
            ?? $skyroomRoomSchedule->end_time;

        if (
            $startTime !== null
            && $endTime !== null
            && $endTime <= $startTime
        ) {
            abort(
                422,
                'زمان پایان باید بعد از زمان شروع باشد.'
            );
        }

        $oldRoomId = (int) $oldRoom->id;

        $skyroomRoomSchedule->update($validated);

        $this->clearRoomCaches($oldRoom);

        if ((int) $newRoom->id !== $oldRoomId) {
            $this->clearRoomCaches($newRoom);
        }

        return $this->jsonResponseOk(
            $skyroomRoomSchedule
                ->fresh()
                ->load([
                    'room.class.academicLevel.academicField',
                ])
        );
    }

    public function destroy(
        Request $request,
        SkyroomRoomSchedule $skyroomRoomSchedule
    ): JsonResponse {
        $skyroomRoomSchedule->loadMissing([
            'room.class.academicLevel.academicField',
        ]);

        $room = $skyroomRoomSchedule->room;

        abort_unless(
            $room instanceof SkyroomRoom,
            422,
            'اتاق مرتبط با زمان‌بندی پیدا نشد.'
        );

        $this->authorizeRoomAccess($room);

        $skyroomRoomSchedule->delete();

        $this->clearRoomCaches($room);

        return $this->jsonResponseOk([
            'message' => 'زمان‌بندی اسکای‌روم با موفقیت حذف شد.',
        ]);
    }

    /**
     * دریافت کاربر احراز هویت‌شده
     */
    private function authenticatedUser(): User
    {
        /** @var User|null $user */
        $user = request()->user();

        abort_unless(
            $user,
            401,
            'کاربر احراز هویت نشده است.'
        );

        return $user;
    }

    /**
     * فقط مدیر و کارمند و ادمین مجاز هستند.
     */
    private function ensureSkyroomManagementRole(User $user): void
    {
        if ($user->hasRole(UserRoleType::Admin->value)) {
            return;
        }

        abort_unless(
            $user->hasAnyRole([
                UserRoleType::Manager->value,
                UserRoleType::Staff->value,
            ]),
            403,
            'شما اجازه مدیریت زمان‌بندی‌های اسکای‌روم را ندارید.'
        );
    }

    /**
     * بررسی دسترسی کاربر به مدرسه مشخص
     */
    private function authorizeSchoolAccess(int $schoolId): void
    {
        $user = $this->authenticatedUser();
        $this->ensureSkyroomManagementRole($user);

        /*
         * ادمین به تمام مدارس دسترسی دارد.
         */
        if ($user->hasRole(UserRoleType::Admin->value)) {
            return;
        }

        /*
         * توجه:
         * عمداً از role_in_school استفاده نشده است،
         * چون این ستون در school_user وجود ندارد.
         */
        $hasAccess = $user->schools()
            ->whereKey($schoolId)
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query
                    ->whereNull('joined_at')
                    ->orWhere('joined_at', '<=', now());
            })
            ->where(function ($query) {
                $query
                    ->whereNull('left_at')
                    ->orWhere('left_at', '>', now());
            })
            ->exists();

        abort_unless(
            $hasAccess,
            403,
            'شما به اسکای‌روم این مدرسه دسترسی ندارید.'
        );
    }

    /**
     * بررسی دسترسی به اتاق از طریق مدرسه کلاس
     */
    private function authorizeRoomAccess(SkyroomRoom $room): void
    {
        $room->loadMissing([
            'class.academicLevel.academicField',
        ]);

        $schoolId = $room
            ->class
            ?->academicLevel
            ?->academicField
            ?->school_id;

        abort_unless(
            $schoolId,
            422,
            'مدرسه اتاق اسکای‌روم قابل تشخیص نیست.'
        );

        $this->authorizeSchoolAccess((int) $schoolId);
    }

    /**
     * دریافت شناسه مدارس مجاز کاربر
     *
     * برای index استفاده می‌شود تا داده مدارس دیگر
     * اصلاً وارد Result Set نشود.
     */
    private function accessibleSchoolIds(User $user)
    {
        return $user->schools()
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query
                    ->whereNull('joined_at')
                    ->orWhere('joined_at', '<=', now());
            })
            ->where(function ($query) {
                $query
                    ->whereNull('left_at')
                    ->orWhere('left_at', '>', now());
            })
            ->pluck('schools.id');
    }

    /**
     * پاک‌سازی کش اتاق و کلاس
     *
     * چون show اتاق شامل schedules است، با تغییر برنامه
     * باید cache جزئیات اتاق نیز پاک شود.
     */
    private function clearRoomCaches(SkyroomRoom $room): void
    {
        Cache::forget(
            "skyroom_room_detail_{$room->id}"
        );

        if ($room->class_id) {
            Cache::forget(
                "school_class_{$room->class_id}_skyroom_rooms"
            );
        }
    }
}
