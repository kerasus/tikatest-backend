<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleType;
use App\Services\SchoolFtpFileService;
use App\Traits\Filter;
use App\Traits\CommonCRUD;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

class SchoolClassController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['externalIndex']);
        $this->middleware('admin_or_permission:classes.view')->only([
            'index',
            'show',
        ]);
        $this->middleware('admin_or_permission:classes.create')->only(['store']);
        $this->middleware('admin_or_permission:classes.update')->only(['update']);
        $this->middleware('admin_or_permission:classes.delete')->only(['destroy']);


        $this->middleware(function ($request, $next) {
            $user = $request->user();

            if (! $user || ! $user->hasRole(UserRoleType::Student->value)) {
                return response()->json([
                    'message' => 'دسترسی فقط برای نقش دانش‌آموز مجاز است.'
                ], Response::HTTP_FORBIDDEN);
            }

            return $next($request);
        })->only(['classFiles', 'classesFilesTree']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['name'],
            'filterKeysExact' => ['academic_level_id'],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'level_name',
                    'relationName' => 'academicLevel',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'school_id',
                    'relationName' => 'academicLevel.academicField',
                    'relationColumn' => 'school_id',
                    'exact' => true,
                ],
            ],
            'filterKeysIn' => [
                'id',
                'academic_level_id',
            ],
            'eagerLoads' => [
                'academicLevel.academicField.school'
            ],
            'returnModelQuery' => true,
        ];

        $result = $this->commonIndex($request, SchoolClass::class, $config);

        if (is_array($result) && isset($result['modelQuery']) && $request->filled('school_id')) {
            $result['modelQuery']->whereHas('academicLevel.academicField', function ($query) use ($request) {
                $query->where('school_id', $request->get('school_id'));
            });
        }

        return $result['responseWithAttachedCollection']($result['modelQuery']);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'academic_level_id' => 'required|exists:academic_levels,id',
            'name' => 'required|string|max:255',
        ]);

        return $this->commonStore($request, SchoolClass::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $class = SchoolClass::with(['academicLevel'])->findOrFail($id);

        return $this->jsonResponseOk($class);
    }

    public function update(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $request->validate([
            'academic_level_id' => 'sometimes|required|exists:academic_levels,id',
            'name' => 'sometimes|required|string|max:255',
        ]);

        return $this->commonUpdate($request, $schoolClass);
    }

    public function destroy(SchoolClass $class): JsonResponse
    {
        // بررسی وجود دانش‌آموز ثبت‌نام شده در این کلاس
        $hasStudents = $class->termEnrollments()->exists();

        if ($hasStudents) {
            return $this->jsonResponseError(
                'امکان حذف این کلاس وجود ندارد؛ زیرا دانش‌آموزانی در آن ثبت‌نام شده‌اند.',
                Response::HTTP_UNPROCESSABLE_ENTITY // کد خطای 422 استاندارد اعتبارسنجی
            );
        }

        return $this->commonDestroy($class);
    }

    public function externalIndex(Request $request): JsonResponse
    {
        // ۱. استخراج مدرسه احراز هویت شده از میدلور (امنیت صددرصدی بدون اتکا به school_id کلاینت)
        $school = $request->attributes->get('current_school');
        $request->merge([
            'school_id' => $school->id,
        ]);
        $config = [
            'filterKeys' => ['name'],
            'filterKeysExact' => ['academic_level_id'],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'level_name',
                    'relationName' => 'academicLevel',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'school_id',
                    'relationName' => 'academicLevel.academicField',
                    'relationColumn' => 'school_id',
                    'exact' => true,
                ],
            ],
            'filterKeysIn' => [
                'id',
                'academic_level_id',
            ],
            'eagerLoads' => [
                'academicLevel.academicField.school'
            ],
            'returnModelQuery' => true,
            'paginate' => false,
        ];

        $result = $this->commonIndex($request, SchoolClass::class, $config);

        if (is_array($result) && isset($result['modelQuery']) && $request->filled('school_id')) {
            $result['modelQuery']->whereHas('academicLevel.academicField', function ($query) use ($request) {
                $query->where('school_id', $request->get('school_id'));
            });
        }

        return $result['responseWithAttachedCollection']($result['modelQuery']);
    }

    public function classFiles(Request $request, int $classId): JsonResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
        ]);

        $result = app(SchoolFtpFileService::class)
            ->getClassFiles($validated['school_id'], $classId);

        return response()->json($result, $result['success'] ? Response::HTTP_OK : ($result['files'] === [] && isset($result['message']) ? Response::HTTP_SERVICE_UNAVAILABLE : Response::HTTP_OK));
    }

    /**
     * دریافت ساختار درختی فایل‌ها برای مجموعه‌ای از کلاس‌ها
     */
    public function classesFilesTree(Request $request, SchoolFtpFileService $schoolFtpFileService): JsonResponse
    {
        $validated = $request->validate([
            'school_id'   => ['required', 'integer', 'exists:schools,id'],
            'class_ids'   => ['required', 'array', 'min:1'],
            'class_ids.*' => ['integer', 'distinct', 'exists:school_classes,id'],
        ]);

        $result = $schoolFtpFileService->getClassesFilesTree(
            $validated['school_id'],
            $validated['class_ids']
        );

        $status = $result['success'] ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return response()->json($result, $status);
    }

}
