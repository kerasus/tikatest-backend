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
            'filterKeysExact' => ['level_id'],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'level_name',
                    'relationName' => 'academicLevel',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
            ],
            'eagerLoads' => ['academicLevel'],
        ];

        $result = $this->commonIndex($request, SchoolClass::class, $config);

        if (is_array($result) && isset($result['modelQuery']) && $request->filled('school_id')) {
            $result['modelQuery']->whereHas('academicLevel', function ($query) use ($request) {
                $query->where('school_id', $request->get('school_id'));
            });
        }

        return $result;
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'level_id' => 'required|exists:academic_levels,id',
            'name' => 'required|string|max:255',
        ]);

        return $this->commonStore($request, SchoolClass::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $class = SchoolClass::with(['academicLevel'])->findOrFail($id);

        return $this->jsonResponseOk($class);
    }

    public function update(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $request->validate([
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
