<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentProfileController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:student_profiles.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:student_profiles.create')->only(['store']);
        $this->middleware('admin_or_permission:student_profiles.update')->only(['update']);
        $this->middleware('admin_or_permission:student_profiles.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['code'],
            'filterRelationIds' => [
                [
                    'requestKey' => 'user_ids',
                    'relationName' => 'user',
                ],
            ],
            'eagerLoads' => ['user', 'guardians'],
        ];

        return $this->commonIndex($request, StudentProfile::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id|unique:student_profiles,user_id',
            'code' => 'nullable|string|max:50|unique:student_profiles,code',
            'xp' => 'integer|min:0',
        ]);

        return $this->commonStore($request, StudentProfile::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $profile = StudentProfile::with(['user', 'guardians'])->findOrFail($id);

        return $this->jsonResponseOk($profile);
    }

    public function update(Request $request, StudentProfile $studentProfile): JsonResponse
    {
        $request->validate([
            'user_id' => 'sometimes|required|exists:users,id|unique:student_profiles,user_id,'.$studentProfile->id,
            'code' => 'nullable|string|max:50|unique:student_profiles,code,'.$studentProfile->id,
            'xp' => 'integer|min:0',
        ]);

        return $this->commonUpdate($request, $studentProfile);
    }

    public function destroy(StudentProfile $studentProfile): JsonResponse
    {
        return $this->commonDestroy($studentProfile);
    }
}
