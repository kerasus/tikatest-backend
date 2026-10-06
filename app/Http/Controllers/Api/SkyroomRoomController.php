<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\SchoolSkyroomAccount;
use App\Models\SkyroomRoom;
use App\Services\SkyroomService;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SkyroomRoomController extends Controller
{
    use CommonCRUD, Filter;

    protected int $cacheTtl = 900;

    public function __construct(protected SkyroomService $skyroom)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:skyroom.rooms.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:skyroom.rooms.manage')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request, $schoolClass): JsonResponse
    {
        $classId = $schoolClass instanceof Model ? $schoolClass->getKey() : $schoolClass;

        $request->merge([
            'class_id' => $classId,
        ]);

        $config = [
            'filterKeys' => [
                'name',
                'title',
                'description',
            ],
            'filterKeysExact' => [
                'class_id',
                'skyroom_account_id',
                'skyroom_id',
                'guest_login',
                'op_login_first',
                'status',
            ],
            'eagerLoads' => [
                'skyroomAccount:id,school_id,title,username,is_active',
                'schedules',
            ],
        ];

        return $this->commonIndex($request, SkyroomRoom::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateRoom($request);
        $this->ensureAccountMatchesClass(
            (int) $validated['class_id'],
            (int) $validated['skyroom_account_id']
        );

        $room = SkyroomRoom::create($validated);
        $this->clearClassRoomsCache($room->class_id, $room->id);

        return $this->jsonResponseOk($room->load('skyroomAccount'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $skyroomRoom = $id instanceof SkyroomRoom ? $id : SkyroomRoom::findOrFail($id);
        $cacheKey = "skyroom_room_detail_{$skyroomRoom->id}";

        $roomData = Cache::remember($cacheKey, $this->cacheTtl, function () use ($skyroomRoom) {
            $skyroomRoom->load(['schedules', 'class.academicLevel', 'skyroomAccount']);

            $liveSkyroomData = null;
            if ($skyroomRoom->skyroom_id && $skyroomRoom->skyroomAccount?->api_key) {
                try {
                    $liveSkyroomData = $this->skyroom
                        ->usingApiKey($skyroomRoom->skyroomAccount->api_key)
                        ->getRoom($skyroomRoom->skyroom_id);
                } catch (Exception $exception) {
                    $liveSkyroomData = [
                        'error' => 'امکان دریافت اطلاعات زنده از اسکای‌روم وجود ندارد: '.$exception->getMessage(),
                    ];
                }
            }

            return [
                'local_room' => $skyroomRoom,
                'live_data' => $liveSkyroomData,
            ];
        });

        return $this->jsonResponseOk($roomData);
    }

    public function update(Request $request, SkyroomRoom $skyroomRoom): JsonResponse
    {
        $validated = $this->validateRoom($request, $skyroomRoom);
        $classId = (int) ($validated['class_id'] ?? $skyroomRoom->class_id);
        $accountId = (int) ($validated['skyroom_account_id'] ?? $skyroomRoom->skyroom_account_id);
        $this->ensureAccountMatchesClass($classId, $accountId);

        $oldClassId = $skyroomRoom->class_id;
        $skyroomRoom->update($validated);
        $this->clearClassRoomsCache($oldClassId, $skyroomRoom->id);
        if ($oldClassId !== $skyroomRoom->class_id) {
            $this->clearClassRoomsCache($skyroomRoom->class_id, $skyroomRoom->id);
        }

        return $this->jsonResponseOk($skyroomRoom->fresh()->load('skyroomAccount'));
    }

    public function destroy(SkyroomRoom $skyroomRoom): JsonResponse
    {
        $classId = $skyroomRoom->class_id;
        $roomId = $skyroomRoom->id;
        $skyroomRoom->delete();
        $this->clearClassRoomsCache($classId, $roomId);

        return $this->jsonResponseOk(['message' => 'اتاق اسکای‌روم با موفقیت حذف شد.']);
    }

    public function generateLoginUrl(Request $request, SkyroomRoom $skyroomRoom): JsonResponse
    {
        $skyroomRoom->loadMissing('skyroomAccount');
        abort_unless($skyroomRoom->skyroom_id, 422, 'شناسه اتاق اسکای‌روم ثبت نشده است.');
        abort_unless($skyroomRoom->skyroomAccount?->is_active, 422, 'اکانت اسکای‌روم غیرفعال است.');

        $user = $request->user();
        $access = $user->hasAnyRole(['admin', 'teacher'])
            ? SkyroomService::ACCESS_OPERATOR
            : SkyroomService::ACCESS_NORMAL;

        $url = $this->skyroom
            ->usingApiKey($skyroomRoom->skyroomAccount->api_key)
            ->createLoginUrl(
                roomId: $skyroomRoom->skyroom_id,
                userId: $user->id,
                nickname: $user->name ?? $user->username,
                access: $access,
                ttl: 3600
            );

        return $this->jsonResponseOk($url);
    }

    private function validateRoom(Request $request, ?SkyroomRoom $room = null): array
    {
        return $request->validate([
            'class_id' => [$room ? 'sometimes' : 'required', 'integer', 'exists:classes,id'],
            'skyroom_account_id' => [
                $room ? 'sometimes' : 'required',
                'integer',
                'exists:school_skyroom_accounts,id',
            ],
            'skyroom_id' => [
                'nullable',
                'integer',
                Rule::unique('skyroom_rooms', 'skyroom_id')->ignore($room?->id),
            ],
            'name' => [$room ? 'sometimes' : 'required', 'string', 'max:255'],
            'title' => [$room ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'max_users' => 'sometimes|integer|min:1|max:65535',
            'guest_login' => 'sometimes|boolean',
            'op_login_first' => 'sometimes|boolean',
            'status' => 'sometimes|boolean',
        ]);
    }

    private function ensureAccountMatchesClass(int $classId, int $accountId): void
    {
        $schoolClass = SchoolClass::with('academicLevel.academicField')->findOrFail($classId);
        $schoolId = $schoolClass->academicLevel?->academicField?->school_id;

        abort_unless(
            $schoolId && $schoolClass->newQuery()
                ->whereKey($classId)
                ->whereHas('academicLevel.academicField', fn ($query) => $query->where('school_id', $schoolId))
                ->exists(),
            422,
            'مدرسه کلاس قابل تشخیص نیست.'
        );

        abort_unless(
            SchoolSkyroomAccount::query()
                ->whereKey($accountId)
                ->where('school_id', $schoolId)
                ->exists(),
            422,
            'اکانت اسکای‌روم باید متعلق به مدرسه همین کلاس باشد.'
        );
    }

    protected function clearClassRoomsCache(int $classId, ?int $roomId = null): void
    {
        Cache::forget("school_class_{$classId}_skyroom_rooms");

        if ($roomId) {
            Cache::forget("skyroom_room_detail_{$roomId}");
        }
    }
}
