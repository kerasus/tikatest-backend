<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:lessons.view')->only(['index', 'show']);
        $this->middleware('permission:lessons.create')->only(['store']);
        $this->middleware('permission:lessons.update')->only(['update']);
        $this->middleware('permission:lessons.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['name'],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'field_name',
                    'relationName' => 'academicField',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'level_name',
                    'relationName' => 'academicLevel',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
            ],
            'eagerLoads' => ['school', 'academicField', 'academicLevel', 'schoolClass'],
        ];

        return $this->commonIndex($request, Lesson::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'name' => 'required|string|max:255',
            'field_id' => 'nullable|exists:academic_fields,id',
            'level_id' => 'nullable|exists:academic_levels,id',
            'class_id' => 'nullable|exists:classes,id',
            'coefficient' => 'nullable|numeric|min:0',
        ]);

        return $this->commonStore($request, Lesson::class);
    }

    public function show(int $id): JsonResponse
    {
        $lesson = Lesson::with(['school', 'academicField', 'academicLevel', 'schoolClass'])->findOrFail($id);

        return $this->jsonResponseOk($lesson);
    }

    public function update(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'name' => 'sometimes|required|string|max:255',
            'field_id' => 'nullable|exists:academic_fields,id',
            'level_id' => 'nullable|exists:academic_levels,id',
            'class_id' => 'nullable|exists:classes,id',
            'coefficient' => 'nullable|numeric|min:0',
        ]);

        return $this->commonUpdate($request, $lesson);
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        return $this->commonDestroy($lesson);
    }
}
