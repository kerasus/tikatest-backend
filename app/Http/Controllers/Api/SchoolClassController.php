<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:classes.view')->only(['index', 'show']);
        $this->middleware('permission:classes.create')->only(['store']);
        $this->middleware('permission:classes.update')->only(['update']);
        $this->middleware('permission:classes.delete')->only(['destroy']);
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
            'eagerLoads' => ['school', 'academicField', 'academicLevel'],
        ];

        return $this->commonIndex($request, SchoolClass::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'field_id' => 'required|exists:academic_fields,id',
            'level_id' => 'required|exists:academic_levels,id',
            'name' => 'required|string|max:255',
        ]);

        return $this->commonStore($request, SchoolClass::class);
    }

    public function show(int $id): JsonResponse
    {
        $class = SchoolClass::with(['school', 'academicField', 'academicLevel'])->findOrFail($id);

        return $this->jsonResponseOk($class);
    }

    public function update(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'field_id' => 'sometimes|required|exists:academic_fields,id',
            'level_id' => 'sometimes|required|exists:academic_levels,id',
            'name' => 'sometimes|required|string|max:255',
        ]);

        return $this->commonUpdate($request, $schoolClass);
    }

    public function destroy(SchoolClass $schoolClass): JsonResponse
    {
        return $this->commonDestroy($schoolClass);
    }
}
