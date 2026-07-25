<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;


use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;

class SchoolClassController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:classes.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:classes.create')->only(['store']);
        $this->middleware('admin_or_permission:classes.update')->only(['update']);
        $this->middleware('admin_or_permission:classes.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['name'],
            'filterKeysExact' => ['school_id'],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'level_name',
                    'relationName' => 'academicLevel',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
            ],
            'eagerLoads' => ['school', 'academicLevel'],
        ];

        return $this->commonIndex($request, SchoolClass::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'level_id' => 'required|exists:academic_levels,id',
            'name' => 'required|string|max:255',
        ]);

        return $this->commonStore($request, SchoolClass::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $class = SchoolClass::with(['school', 'academicLevel'])->findOrFail($id);

        return $this->jsonResponseOk($class);
    }

    public function update(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
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
