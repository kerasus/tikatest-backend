<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InPersonExamResult;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InPersonExamResultController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:exams.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:exams.create')->only(['store']);
        $this->middleware('admin_or_permission:exams.update')->only(['update']);
        $this->middleware('admin_or_permission:exams.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeysExact' => ['in_person_exam_id', 'user_id', 'recorded_by'],
            'eagerLoads' => ['inPersonExamDetail', 'inPersonExamDetail.exam', 'inPersonExamDetail.exam.term', 'student', 'recordedBy'],
        ];

        return $this->commonIndex($request, InPersonExamResult::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'in_person_exam_id' => 'required|exists:in_person_exam_details,id',
            'user_id' => 'required|exists:users,id',
            'raw_score' => 'required|numeric|min:0',
            'scaled_score' => 'required|numeric|min:0',
            'recorded_by' => 'nullable|exists:users,id',
            'z_score' => 'nullable|numeric',
        ]);

        return $this->commonStore($request, InPersonExamResult::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $result = InPersonExamResult::with(['inPersonExamDetail', 'inPersonExamDetail.exam', 'inPersonExamDetail.exam.term', 'student', 'recordedBy'])->findOrFail($id);

        return $this->jsonResponseOk($result);
    }

    public function update(Request $request, InPersonExamResult $inPersonExamResult): JsonResponse
    {
        $request->validate([
            'in_person_exam_id' => 'sometimes|required|exists:in_person_exam_details,id',
            'user_id' => 'sometimes|required|exists:users,id',
            'raw_score' => 'sometimes|required|numeric|min:0',
            'scaled_score' => 'sometimes|required|numeric|min:0',
            'recorded_by' => 'nullable|exists:users,id',
            'z_score' => 'nullable|numeric',
        ]);

        return $this->commonUpdate($request, $inPersonExamResult);
    }

    public function destroy(InPersonExamResult $inPersonExamResult): JsonResponse
    {
        return $this->commonDestroy($inPersonExamResult);
    }
}
