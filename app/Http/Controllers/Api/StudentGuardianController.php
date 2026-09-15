<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\UserRoleType;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentGuardianController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:student_guardians.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:student_guardians.create')->only(['store', 'createWithUser']);
        $this->middleware('admin_or_permission:student_guardians.update')->only(['update']);
        $this->middleware('admin_or_permission:student_guardians.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['relationship_type', 'job'],
            'filterKeysExact' => ['is_primary_contact'],
            'filterRelationIds' => [
                [
                    'requestKey' => 'user_ids',
                    'relationName' => 'user',
                ],
                [
                    'requestKey' => 'student_profile_ids',
                    'relationName' => 'studentProfile',
                ],
            ],
            'eagerLoads' => ['user', 'studentProfile'],
        ];

        return $this->commonIndex($request, StudentGuardian::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'student_profile_id' => 'required|exists:student_profiles,id',
            'relationship_type' => 'required|in:father,mother,guardian',
            'job' => 'nullable|string|max:255',
            'is_primary_contact' => 'boolean',
        ]);

        return $this->commonStore($request, StudentGuardian::class);
    }

    public function createWithUser(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'mobile' => 'nullable|string|max:20|unique:users,mobile',
            'password' => 'required|string|min:6',
            'student_profile_id' => 'required|exists:student_profiles,id',
            'relationship_type' => 'required|in:father,mother,guardian',
            'job' => 'nullable|string|max:255',
            'is_primary_contact' => 'boolean',
        ]);

        $username = strtolower(
            trim($request->input('first_name').'.'.$request->input('last_name'))
        ).'.'.mt_rand(1000, 9999);

        $user = User::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'username' => $username,
            'mobile' => $request->input('mobile'),
            'password' => $request->input('password'),
        ]);
        $user->assignRole(UserRoleType::Guardian->value);

        $guardian = StudentGuardian::create([
            'user_id' => $user->id,
            'student_profile_id' => $request->input('student_profile_id'),
            'relationship_type' => $request->input('relationship_type'),
            'job' => $request->input('job'),
            'is_primary_contact' => $request->boolean('is_primary_contact'),
        ]);

        return $this->jsonResponseOk($guardian->load('user', 'studentProfile'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $guardian = StudentGuardian::with(['user', 'studentProfile'])->findOrFail($id);

        return $this->jsonResponseOk($guardian);
    }

    public function update(Request $request, StudentGuardian $studentGuardian): JsonResponse
    {
        $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'student_profile_id' => 'sometimes|required|exists:student_profiles,id',
            'relationship_type' => 'sometimes|required|in:father,mother,guardian',
            'job' => 'nullable|string|max:255',
            'is_primary_contact' => 'boolean',
        ]);

        return $this->commonUpdate($request, $studentGuardian);
    }

    public function destroy(StudentGuardian $studentGuardian): JsonResponse
    {
        return $this->commonDestroy($studentGuardian);
    }
}
