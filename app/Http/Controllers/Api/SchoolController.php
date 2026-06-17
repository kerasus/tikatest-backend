<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:schools.view')->only(['index', 'show']);
        $this->middleware('permission:schools.create')->only(['store']);
        $this->middleware('permission:schools.update')->only(['update']);
        $this->middleware('permission:schools.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'code',
                'name',
                'type',
            ],
            'filterKeysExact' => [
                'type',
            ],
        ];

        return $this->commonIndex($request, School::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:20|unique:schools,code',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'logo_url' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:school,institute',
            'account_url' => 'nullable|string|max:255',
        ]);

        return $this->commonStore($request, School::class);
    }

    public function show(int $id): JsonResponse
    {
        $school = School::findOrFail($id);

        return $this->jsonResponseOk($school);
    }

    public function update(Request $request, School $school): JsonResponse
    {
        $request->validate([
            'code' => 'sometimes|required|string|max:20|unique:schools,code,' . $school->id,
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'logo_url' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:school,institute',
            'account_url' => 'nullable|string|max:255',
        ]);

        return $this->commonUpdate($request, $school);
    }

    public function destroy(School $school): JsonResponse
    {
        return $this->commonDestroy($school);
    }
}
