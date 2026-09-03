<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamCategoryTermLimit;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamCategoryTermLimitController extends Controller
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
            'filterKeysExact' => ['exam_category_id', 'term_id'],
            'eagerLoads' => ['examCategory', 'term.school', 'term'],
        ];

        return $this->commonIndex($request, ExamCategoryTermLimit::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'exam_category_id' => 'required|exists:exam_categories,id',
            'term_id' => 'required|exists:academic_terms,id',
            'max_occurrences' => 'nullable|integer|min:0',
        ]);

        return $this->commonStore($request, ExamCategoryTermLimit::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $limit = ExamCategoryTermLimit::with(['examCategory', 'term.school', 'term'])->findOrFail($id);

        return $this->jsonResponseOk($limit);
    }

    public function update(Request $request, ExamCategoryTermLimit $limit): JsonResponse
    {
        $request->validate([
            'exam_category_id' => 'sometimes|required|exists:exam_categories,id',
            'term_id' => 'sometimes|required|exists:academic_terms,id',
            'max_occurrences' => 'nullable|integer|min:0',
        ]);

        return $this->commonUpdate($request, $limit);
    }

    public function destroy(ExamCategoryTermLimit $limit): JsonResponse
    {
        return $this->commonDestroy($limit);
    }
}
