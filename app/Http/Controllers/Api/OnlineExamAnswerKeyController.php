<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnlineExamAnswerKey;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlineExamAnswerKeyController extends Controller
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
            'filterKeysExact' => ['exam_id', 'is_active', 'has_negative_mark'],
            'eagerLoads' => ['exam', 'exam.category', 'exam.lesson'],
        ];

        return $this->commonIndex($request, OnlineExamAnswerKey::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'question_number' => 'required|integer|min:1',
            'correct_option' => 'required|string|max:10',
            'weight' => 'nullable|numeric|min:0',
            'has_negative_mark' => 'boolean',
            'is_active' => 'boolean',
        ]);

        return $this->commonStore($request, OnlineExamAnswerKey::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $key = OnlineExamAnswerKey::with(['exam', 'exam.category', 'exam.lesson'])->findOrFail($id);

        return $this->jsonResponseOk($key);
    }

    public function update(Request $request, OnlineExamAnswerKey $onlineExamAnswerKey): JsonResponse
    {
        $request->validate([
            'exam_id' => 'sometimes|required|exists:exams,id',
            'question_number' => 'sometimes|required|integer|min:1',
            'correct_option' => 'sometimes|required|string|max:10',
            'weight' => 'nullable|numeric|min:0',
            'has_negative_mark' => 'boolean',
            'is_active' => 'boolean',
        ]);

        return $this->commonUpdate($request, $onlineExamAnswerKey);
    }

    public function destroy(OnlineExamAnswerKey $onlineExamAnswerKey): JsonResponse
    {
        return $this->commonDestroy($onlineExamAnswerKey);
    }
}
