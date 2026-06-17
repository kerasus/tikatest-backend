<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryCase;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisciplinaryCaseController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:disciplinary_cases.view')->only(['index', 'show']);
        $this->middleware('permission:disciplinary_cases.create')->only(['store']);
        $this->middleware('permission:disciplinary_cases.update')->only(['update']);
        $this->middleware('permission:disciplinary_cases.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['name'],
            'eagerLoads' => ['school'],
        ];

        return $this->commonIndex($request, DisciplinaryCase::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        return $this->commonStore($request, DisciplinaryCase::class);
    }

    public function show(int $id): JsonResponse
    {
        $case = DisciplinaryCase::with('school')->findOrFail($id);

        return $this->jsonResponseOk($case);
    }

    public function update(Request $request, DisciplinaryCase $disciplinaryCase): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        return $this->commonUpdate($request, $disciplinaryCase);
    }

    public function destroy(DisciplinaryCase $disciplinaryCase): JsonResponse
    {
        return $this->commonDestroy($disciplinaryCase);
    }
}
