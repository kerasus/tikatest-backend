<?php

namespace App\Http\Controllers\Api;

use App\Traits\Filter;
use App\Traits\CommonCRUD;
use App\Enums\UserRoleType;
use App\Models\ExamCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ExamCategoryController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:exam_categories.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:exam_categories.create')->only(['store']);
        $this->middleware('admin_or_permission:exam_categories.update')->only(['update']);
        $this->middleware('admin_or_permission:exam_categories.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'title'
            ],
            'filterKeysExact' => [
                'id',
                'is_system',
                'term_number',
                'school_id'
            ],
            'filterKeysIn' => [
                'id',
            ],
            'scopes' => [
                'forSchoolOrGlobal'
            ],
            'eagerLoads' => [
                'school'
            ],
        ];

        return $this->commonIndex($request, ExamCategory::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|exists:schools,id',
            'title' => 'required|string|max:255',
            'term_number' => 'nullable|integer|in:1,2',
            'sort_order' => 'nullable|integer|min:0',
            'is_system' => 'boolean',
        ]);

        return $this->commonStore($request, ExamCategory::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $category = ExamCategory::with(['school'])->findOrFail($id);

        return $this->jsonResponseOk($category);
    }

    public function update(Request $request, ExamCategory $examCategory): JsonResponse
    {
        $request->validate([
            'school_id' => 'sometimes|nullable|exists:schools,id',
            'title' => 'sometimes|required|string|max:255',
            'term_number' => 'nullable|integer|in:1,2',
            'sort_order' => 'nullable|integer|min:0',
            'is_system' => 'boolean',
        ]);

        if ($examCategory->is_system && ! $request->user()->hasRole(UserRoleType::Admin->value)) {
            return $this->jsonResponseError('فقط ادمین‌ها می‌توانند دسته‌بندی سیستمی را ویرایش کنند.', 403);
        }

        return $this->commonUpdate($request, $examCategory);
    }

    public function destroy(Request $request, ExamCategory $examCategory): JsonResponse
    {
        if ($examCategory->is_system && ! $request->user()->hasRole(UserRoleType::Admin->value)) {
            return $this->jsonResponseError('فقط ادمین‌ها می‌توانند دسته‌بندی سیستمی را حذف کنند.', 403);
        }

        return $this->commonDestroy($examCategory);
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. استخراج لیست آیدی مدارسی که کاربر در آن‌ها ثبت‌نام دارد
        $allowedSchoolIds = $user ? $user->termEnrollments()
            ->whereNotNull('school_id')
            ->pluck('school_id')
            ->unique()
            ->toArray() : [];

        // 2. ولیدیشن دقیق ریکوئست
        $validator = Validator::make($request->all(), [
            'school_id' => [
                'required',
                'integer',
                'exists:schools,id',
                Rule::in($allowedSchoolIds), // چک می‌کند که school_id حتماً متعلق به این دانش‌آموز باشد
            ],
        ], [
            'school_id.required' => 'شناسه مدرسه الزامی است.',
            'school_id.exists'   => 'مدرسه انتخاب شده نامعتبر است.',
            'school_id.in'       => 'شما به دسته‌بندی‌های این مدرسه دسترسی ندارید.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $config = [
            'filterKeys' => [
                'title'
            ],
            'filterKeysExact' => [
                'id',
                'is_system',
                'term_number',
                'school_id'
            ],
            'filterKeysIn' => [
                'id',
            ],
            'scopes' => [
                'forSchoolOrGlobal'
            ],
            'eagerLoads' => [
                'school'
            ],
        ];

        return $this->commonIndex($request, ExamCategory::class, $config);
    }
}
