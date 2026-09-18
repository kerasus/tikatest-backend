<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\SchoolClass;
use App\Models\SkyroomRoom;
use Illuminate\Http\Request;
use App\Services\SkyroomService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class SkyroomRoomController extends Controller
{
    protected SkyroomService $skyroom;
    // مدت زمان نگهداری کش به ثانیه (مثلاً ۱۵ دقیقه)
    protected int $cacheTtl = 900;

    public function __construct(SkyroomService $skyroom)
    {
        $this->middleware('auth:sanctum');
        $this->skyroom = $skyroom;

        $this->middleware('admin_or_permission:skyroom.rooms.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:skyroom.rooms.manage')->only(['store', 'update', 'destroy']);
    }

    /**
     * لیست اتاق‌های اسکای‌روم یک کلاس به همراه زمان‌بندی‌ها (با Cache)
     */
    public function index(SchoolClass $schoolClass): JsonResponse
    {
        $cacheKey = "school_class_{$schoolClass->id}_skyroom_rooms";

        // دریافت از کش یا ذخیره مجدد در صورت عدم وجود
        $rooms = Cache::remember($cacheKey, $this->cacheTtl, function () use ($schoolClass) {
            return $schoolClass->skyroomRooms()
                ->with(['schedules' => function ($query) {
                    $query->where('is_active', true);
                }])
                ->get();
        });

        return $this->jsonResponseOk($rooms);
    }

    /**
     * دریافت جزئیات یک اتاق لوکال + استعلام وضعیت زنده آن از اسکای‌روم (با Cache)
     */
    public function show(Request $request, $id): JsonResponse
    {
        $cacheKey = "skyroom_room_detail_{$id}";

        $roomData = Cache::remember($cacheKey, $this->cacheTtl, function () use ($id) {
            // دریافت مدل به همراه روابط
            $skyroomRoom = SkyroomRoom::with(['schedules', 'class.academicLevel'])->findOrFail($id);

            // استعلام مشخصات زنده اتاق از وب‌سرویس اسکای‌روم
            $liveSkyroomData = null;
            if ($skyroomRoom->skyroom_id) {
                try {
                    $liveSkyroomData = $this->skyroom->getRoom($skyroomRoom->skyroom_id);
                } catch (Exception $e) {
                    $liveSkyroomData = ['error' => 'امکان دریافت اطلاعات زنده از اسکای‌روم وجود ندارد: ' . $e->getMessage()];
                }
            }

            return [
                'local_room' => $skyroomRoom,
                'live_data'  => $liveSkyroomData,
            ];
        });

        return $this->jsonResponseOk($roomData);
    }

    /**
     * صدور لینک ورود مستقیم به اتاق
     */
    public function generateLoginUrl(Request $request, SkyroomRoom $skyroomRoom): JsonResponse
    {
        $user = $request->user();

        // تعیین سطح دسترسی بر اساس نقش کاربر
        $access = $user->hasAnyRole(['admin', 'teacher'])
            ? SkyroomService::ACCESS_OPERATOR
            : SkyroomService::ACCESS_NORMAL;

        $url = $this->skyroom->createLoginUrl(
            roomId: $skyroomRoom->skyroom_id,
            userId: $user->id,
            nickname: $user->name ?? $user->username,
            access: $access,
            ttl: 3600
        );

        return $this->jsonResponseOk($url);
    }

    /**
     * متد کمکی برای پاکسازی کش‌های مربوط به کلاس و اتاق
     */
    protected function clearClassRoomsCache(int $classId, ?int $roomId = null): void
    {
        Cache::forget("school_class_{$classId}_skyroom_rooms");

        if ($roomId) {
            Cache::forget("skyroom_room_detail_{$roomId}");
        }
    }
}
