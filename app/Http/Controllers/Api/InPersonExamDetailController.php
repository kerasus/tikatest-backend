<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InPersonExamDetail;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InPersonExamDetailController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:exams.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:exams.update')->only(['update']);
        $this->middleware('admin_or_permission:exams.create')->only(['store']);
        $this->middleware('admin_or_permission:exams.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeysExact' => ['exam_id'],
            'filterDate' => ['held_at', 'created_at'],
            'eagerLoads' => ['exam', 'exam.category', 'exam.lesson', 'createdBy'],
        ];

        return $this->commonIndex($request, InPersonExamDetail::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id|unique:in_person_exam_details,exam_id',
            'held_at' => 'nullable|date',
            'is_descriptive' => 'boolean',
            'created_by' => 'nullable|exists:users,id',
        ]);

        return $this->commonStore($request, InPersonExamDetail::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $detail = InPersonExamDetail::with(['exam', 'exam.category', 'exam.lesson', 'createdBy'])->findOrFail($id);

        return $this->jsonResponseOk($detail);
    }

    public function update(Request $request, InPersonExamDetail $inPersonExamDetail): JsonResponse
    {
        $request->validate([
            'exam_id' => 'sometimes|required|exists:exams,id|unique:in_person_exam_details,exam_id,'.$inPersonExamDetail->id,
            'held_at' => 'nullable|date',
            'is_descriptive' => 'boolean',
            'created_by' => 'nullable|exists:users,id',
        ]);

        return $this->commonUpdate($request, $inPersonExamDetail);
    }

    public function destroy(InPersonExamDetail $inPersonExamDetail): JsonResponse
    {
        return $this->commonDestroy($inPersonExamDetail);
    }
}
