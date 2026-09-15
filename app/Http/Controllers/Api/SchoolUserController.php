<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolUser;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolUserController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:school_users.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:school_users.create')->only(['store']);
        $this->middleware('admin_or_permission:school_users.update')->only(['update']);
        $this->middleware('admin_or_permission:school_users.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['personnel_code'],
            'filterKeysExact' => ['is_active', 'school_id', 'user_id'],
            'filterRelationIds' => [
                [
                    'requestKey' => 'school_ids',
                    'relationName' => 'school',
                ],
                [
                    'requestKey' => 'user_ids',
                    'relationName' => 'user',
                ],
            ],
            'eagerLoads' => ['user', 'school'],
        ];

        return $this->commonIndex($request, SchoolUser::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|exists:schools,id',
            'user_id' => 'required|exists:users,id',
            'personnel_code' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'joined_at' => 'nullable|date',
            'left_at' => 'nullable|date|after:joined_at',
        ]);

        return $this->commonStore($request, SchoolUser::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $schoolUser = SchoolUser::with(['user', 'school'])->findOrFail($id);

        return $this->jsonResponseOk($schoolUser);
    }

    public function update(Request $request, SchoolUser $schoolUser): JsonResponse
    {
        $request->validate([
            'school_id' => 'sometimes|required|exists:schools,id',
            'user_id' => 'sometimes|required|exists:users,id',
            'personnel_code' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'joined_at' => 'nullable|date',
            'left_at' => 'nullable|date|after:joined_at',
        ]);

        return $this->commonUpdate($request, $schoolUser);
    }

    public function destroy(SchoolUser $schoolUser): JsonResponse
    {
        return $this->commonDestroy($schoolUser);
    }
}
