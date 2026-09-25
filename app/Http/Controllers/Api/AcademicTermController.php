<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\School;
use App\Services\TermService;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicTermController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:terms.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:terms.create')->only(['store']);
        $this->middleware('admin_or_permission:terms.update')->only(['update']);
        $this->middleware('admin_or_permission:terms.delete')->only(['destroy']);
    }

    public function index(Request $request, $school): JsonResponse
    {
        // اگر روت مدل بایندینگ فعال باشه و آبجکت اومده باشه ID رو برمی‌داره، وگرنه خود مقدار رو ست می‌کنه
        $schoolId = $school instanceof Model ? $school->getKey() : $school;

        // ادغام کردن school_id در کوئری/اینپوت‌های ریکوئست
        $request->merge([
            'school_id' => $schoolId,
        ]);

        $config = [
            'filterKeys' => [
                'name',
            ],
            'filterKeysExact' => [
                'school_id',
                'is_active',
            ],
            'eagerLoads' => [
                'children.children',
                'parentTerm',
            ],
        ];

        return $this->commonIndex($request, AcademicTerm::class, $config);
    }

    public function store(Request $request, School $school, TermService $termService): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:school_year,seasonal,sub_term',
            'academic_year' => 'nullable|string|max:20',
            'season' => 'nullable|string|max:20',
            'period' => 'nullable|integer',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'is_active' => 'nullable|boolean',
            'parent_id' => [
                'nullable',
                Rule::exists('academic_terms', 'id')->where('school_id', $school->id),
            ],
        ]);

        $term = DB::transaction(function () use ($validated, $school, $termService) {
            return $termService->create(array_merge($validated, [
                'school_id' => $school->id,
            ]));
        });

        return $this->jsonResponseOk($term, 201);
    }

    public function showTerm(Request $request, School $school, AcademicTerm $term): JsonResponse
    {
        return $this->jsonResponseOk(
            $term->load(['children.children', 'parentTerm'])
        );
    }

    public function update(Request $request, School $school, AcademicTerm $term, TermService $termService): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|in:school_year,seasonal,sub_term',
            'academic_year' => 'nullable|string|max:20',
            'season' => 'nullable|string|max:20',
            'period' => 'nullable|integer',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'is_active' => 'nullable|boolean',
            'parent_id' => [
                'nullable',
                Rule::exists('academic_terms', 'id')->where('school_id', $school->id),
            ],
        ]);

        $term = DB::transaction(function () use ($term, $validated, $termService) {
            return $termService->update($term, $validated);
        });

        return $this->jsonResponseOk($term);
    }

    public function destroy(Request $request, School $school, AcademicTerm $term): JsonResponse
    {
        $term->delete();

        return $this->jsonResponseOk([
            'message' => 'ترم با موفقیت حذف شد.',
        ]);
    }
}
