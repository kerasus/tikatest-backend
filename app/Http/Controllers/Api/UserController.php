<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Traits\Filter;
use App\Traits\CommonCRUD;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    use CommonCRUD, Filter;

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
                'first_name',
                'last_name',
                'username',
                'email',
                'mobile',
            ],
            'filterOnMultipleColumnKeys' => [
                [
                    'requestKey' => 'full_name',
                    'columns' => [
                        'first_name',
                        'last_name',
                    ],
                ],
            ],
            'scopes' => [
                'role',
                'nonStudent',
            ],
            'filterRelationIds' => [
                [
                    'requestKey'   => 'school_id',
                    'relationName' => 'schools',
                ],
            ],
            'eagerLoads' => ['roles', 'permissions'],
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
        })->with(['roles', 'permissions'])->get();

        return $this->jsonResponseOk($users);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|unique:users',
            'mobile' => 'required|string|unique:users',
            'email' => 'nullable|string|email|unique:users',
            'password' => 'required|string|min:6',
            'picture' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:2048',
        ]);

        $data = $request->only([
            'first_name', 'last_name', 'username', 'mobile', 'email', 'password',
        ]);

        if ($request->hasFile('picture')) {
            $data['picture'] = $request->file('picture')->store('user-pictures', 'public');
        }

        $user = User::create($data);

        return $this->jsonResponseOk($user);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $user = User::with(['roles', 'permissions', 'schools'])->findOrFail($id);

        return $this->jsonResponseOk($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|unique:users,username,'.$user->id,
            'mobile' => 'sometimes|required|string|unique:users,mobile,'.$user->id,
            'email' => 'nullable|string|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:6',
            'picture' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:2048',
        ]);

        $data = $request->only([
            'first_name', 'last_name', 'username', 'mobile', 'email',
        ]);

        // اگر پسورد پر شده بود آپدیتش کن، اگر خالی بود پسورد قبلی رو بازنویسی نکن!
        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        // مدیریت فایل تصویر (حذف عکس قدیمی + ذخیره عکس جدید)
        if ($request->hasFile('picture')) {
            if ($user->picture && Storage::disk('public')->exists($user->picture)) {
                Storage::disk('public')->delete($user->picture);
            }

            $data['picture'] = $request->file('picture')->store('user-pictures', 'public');
        }

        $user->fill($data);
        $user->save();

        return $this->jsonResponseOk($user);
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

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // لود تنبل/Lazy Load رابطه‌ها روی کاربر لاگین‌شده
        $user->load(['roles', 'permissions', 'schools']);

        return $this->jsonResponseOk($user);
    }
}
