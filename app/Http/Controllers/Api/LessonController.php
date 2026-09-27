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
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:lessons.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:lessons.create')->only(['store']);
        $this->middleware('admin_or_permission:lessons.update')->only(['update']);
        $this->middleware('admin_or_permission:lessons.delete')->only(['destroy']);
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
                    'requestKey' => 'class_id',
                    'relationName' => 'classes',
                    'relationColumn' => 'classes.id',
                    'exact' => true,
                ],
                [
                    'requestKey' => 'field_id',
                    'relationName' => 'academicLevel.academicField',
                    'relationColumn' => 'academic_fields.id',
                    'exact' => true,
                ],
                [
                    'requestKey' => 'school_id',
                    'relationName' => 'academicLevel.academicField.school',
                    'relationColumn' => 'schools.id',
                    'exact' => true,
                ],
            ],
            'scopes' => [
                'forClassWithFallback',
            ],
            'filterKeysIn' => [
                'id',
            ],
            'eagerLoads' => ['academicLevel.academicField.school'],
        ];

        return $this->commonIndex($request, Lesson::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'academic_level_id' => 'nullable|exists:academic_levels,id',
            'coefficient' => 'nullable|numeric|min:0',
        ]);

        return $this->commonStore($request, Lesson::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $lesson = Lesson::with(['academicLevel'])->findOrFail($id);

        return $this->jsonResponseOk($lesson);
    }

    public function update(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'academic_level_id' => 'nullable|exists:academic_levels,id',
            'coefficient' => 'nullable|numeric|min:0',
        ]);

        return $this->commonUpdate($request, $lesson);
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        return $this->commonDestroy($lesson);
    }
}
