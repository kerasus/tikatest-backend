<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TermController extends Controller
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

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['name'],
            'filterKeysExact' => ['school_id', 'type', 'parent_id', 'is_active'],
            'filterDate' => ['starts_at', 'ends_at'],
            'eagerLoads' => ['school', 'parentTerm', 'children'],
        ];

        return $this->commonIndex($request, AcademicTerm::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateTerm($request);

        return $this->commonStore($request, AcademicTerm::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $term = AcademicTerm::with(['school', 'parentTerm', 'children', 'termLimits', 'enrollments'])->findOrFail($id);

        return $this->jsonResponseOk($term);
    }

    public function update(Request $request, AcademicTerm $term): JsonResponse
    {
        $this->validateTerm($request, true);

        return $this->commonUpdate($request, $term);
    }

    public function destroy(AcademicTerm $term): JsonResponse
    {
        return $this->commonDestroy($term);
    }

    protected function validateTerm(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'school_id' => ($isUpdate ? 'sometimes' : 'required') . '|exists:schools,id',
            'name' => ($isUpdate ? 'sometimes' : 'required') . '|string|max:255',
            'type' => 'sometimes|in:school_year,seasonal,sub_term',
            'academic_year' => 'nullable|string|max:9',
            'season' => 'nullable|string|max:20',
            'period' => 'nullable|integer|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'is_active' => 'sometimes|boolean',
            'parent_id' => 'nullable|exists:academic_terms,id',
        ];

        return $request->validate($rules);
    }
}
