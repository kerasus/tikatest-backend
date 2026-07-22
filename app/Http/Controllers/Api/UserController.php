<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;


use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:users.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:users.create')->only(['store']);
        $this->middleware('admin_or_permission:users.update')->only(['update']);
        $this->middleware('admin_or_permission:users.delete')->only(['destroy']);
        $this->middleware('admin_or_permission:users.manage-roles')->only(['assignRole', 'removeRole']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'firstname',
                'lastname',
                'username',
                'employee_code',
                'email',
                'mobile',
            ],
            'filterOnMultipleColumnKeys' => [
                [
                    'requestKey' => 'full_name',
                    'columns' => [
                        'firstname',
                        'lastname',
                    ],
                ],
            ],
            'scopes' => [
                'role',
            ],
            'eagerLoads' => ['roles', 'permissions', 'schools'],
        ];

        return $this->commonIndex($request, User::class, $config);
    }

    public function getByRole(Request $request, string $role): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $users = User::whereHas('roles', function ($query) use ($role) {
            $query->where('name', $role);
        })->with(['roles', 'permissions', 'schools'])->get();

        return $this->jsonResponseOk($users);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'username' => 'required|string|unique:users',
            'mobile' => 'required|string|unique:users',
            'email' => 'nullable|string|email|unique:users',
            'employee_code' => 'nullable|string|unique:users',
            'password' => 'required|string|min:6',
        ]);

        return $this->commonStore($request, User::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $user = User::with(['roles', 'permissions', 'schools'])->findOrFail($id);

        return $this->jsonResponseOk($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'firstname' => 'sometimes|required|string|max:255',
            'lastname' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|unique:users,username,' . $user->id,
            'mobile' => 'sometimes|required|string|unique:users,mobile,' . $user->id,
            'email' => 'nullable|string|email|unique:users,email,' . $user->id,
            'employee_code' => 'nullable|string|unique:users,employee_code,' . $user->id,
            'password' => 'nullable|string|min:6',
        ]);

        return $this->commonUpdate($request, $user);
    }

    public function destroy(User $user): JsonResponse
    {
        return $this->commonDestroy($user);
    }

    public function assignRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $user->assignRole($request->input('role'));

        return response()->json([
            'message' => 'نقش کاربر با موفقیت اختصاص داده شد.',
            'data' => [
                'user' => $user->load('roles', 'permissions'),
            ],
        ]);
    }

    public function removeRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $user->removeRole($request->input('role'));

        return response()->json([
            'message' => 'نقش کاربر با موفقیت حذف شد.',
            'data' => [
                'user' => $user->load('roles', 'permissions'),
            ],
        ]);
    }

    public function getSchools(Request $request, User $user): JsonResponse
    {
        $schools = $user->schools()->get();

        return $this->jsonResponseOk($schools);
    }

    public function assignSchool(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|exists:schools,id',
            'role' => 'nullable|string|max:50',
        ]);

        $user->schools()->attach($request->input('school_id'), [
            'role' => $request->input('role'),
        ]);

        return response()->json([
            'message' => 'مدرسه با موفقیت به کاربر اضافه شد.',
            'data' => [
                'user' => $user->load('roles', 'permissions', 'schools'),
            ],
        ]);
    }

    public function removeSchool(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'school_id' => 'required|exists:schools,id',
        ]);

        $user->schools()->detach($request->input('school_id'));

        return response()->json([
            'message' => 'مدرسه با موفقیت از کاربر حذف شد.',
            'data' => [
                'user' => $user->load('roles', 'permissions', 'schools'),
            ],
        ]);
    }
}
