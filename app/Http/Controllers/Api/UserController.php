<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleType;
use App\Http\Controllers\Controller;
use App\Models\SchoolUser;
use App\Models\User;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class UserController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:users.view')->only(['index', 'show']);
        $this->middleware('role:'.UserRoleType::Admin->value.'|'.UserRoleType::Manager->value)->only(['store']);
        $this->middleware('admin_or_permission:users.update')->only(['update']);
        $this->middleware('admin_or_permission:users.delete')->only(['destroy']);
        $this->middleware('role:'.UserRoleType::Admin->value.'|'.UserRoleType::Manager->value)
            ->only(['assignRole', 'removeRole']);
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
            'filterKeysIn' => [
                'id',
            ],
            'scopes' => [
                'role',
                'nonStudent',
            ],
            'filterRelationIds' => [
                [
                    'requestKey' => 'school_id',
                    'relationName' => 'schools',
                ],
            ]
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
        /** @var User $creator */
        $creator = $request->user();
        $isSystemAdmin = $creator->hasRole(UserRoleType::Admin->value);
        $picturePath = null;

        try {
            $user = DB::transaction(function () use ($request, $creator, $isSystemAdmin, &$picturePath) {
                $schoolIdRules = $isSystemAdmin
                    ? ['nullable', 'integer', 'exists:schools,id']
                    : [
                        'required',
                        'integer',
                        Rule::exists('school_user', 'school_id')
                            ->where(fn ($query) => $query
                                ->where('user_id', $creator->id)
                                ->where('is_active', true)),
                    ];

                $request->validate([
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'username' => 'required|string|unique:users',
                    'mobile' => 'required|string|unique:users',
                    'email' => 'nullable|string|email|unique:users',
                    'password' => 'required|string|min:6',
                    'picture' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:2048',
                    'school_id' => $schoolIdRules,
                ]);

                $data = $request->only([
                    'first_name', 'last_name', 'username', 'mobile', 'email', 'password',
                ]);

                if ($request->hasFile('picture')) {
                    $picturePath = $request->file('picture')->store('user-pictures', 'public');
                    $data['picture'] = $picturePath;
                }

                $user = User::create($data);

                if (! $isSystemAdmin) {
                    SchoolUser::create([
                        'school_id' => $request->integer('school_id'),
                        'user_id' => $user->id,
                        'is_active' => true,
                    ]);
                }

                return $user;
            });
        } catch (Throwable $exception) {
            if ($picturePath !== null) {
                Storage::disk('public')->delete($picturePath);
            }

            throw $exception;
        }

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

        $role = $request->string('role')->toString();
        $accessError = $this->getRoleManagementAccessError($request->user(), $user, $role);
        if ($accessError !== null) {
            return $accessError;
        }

        $user->assignRole($role);

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

        $role = $request->string('role')->toString();
        $accessError = $this->getRoleManagementAccessError($request->user(), $user, $role);
        if ($accessError !== null) {
            return $accessError;
        }

        $user->removeRole($role);

        return response()->json([
            'message' => 'نقش کاربر با موفقیت حذف شد.',
            'data' => [
                'user' => $user->load('roles', 'permissions'),
            ],
        ]);
    }

    private function getRoleManagementAccessError(User $actor, User $targetUser, string $role): ?JsonResponse
    {
        if ($actor->hasRole(UserRoleType::Admin->value)) {
            return null;
        }

        if (! $actor->hasRole(UserRoleType::Manager->value)) {
            return $this->jsonResponseError('شما مجاز به مدیریت نقش‌های کاربران نیستید.', 403);
        }

        if ($role === UserRoleType::Admin->value) {
            return $this->jsonResponseError('مدیر مدرسه مجاز به مدیریت نقش مدیرکل نیست.', 403);
        }

        $hasSharedActiveSchool = SchoolUser::query()
            ->where('user_id', $targetUser->id)
            ->where('is_active', true)
            ->whereIn('school_id', SchoolUser::query()
                ->select('school_id')
                ->where('user_id', $actor->id)
                ->where('is_active', true))
            ->exists();

        if (! $hasSharedActiveSchool) {
            return $this->jsonResponseError('شما فقط می‌توانید نقش کاربران مدرسه خود را مدیریت کنید.', 403);
        }

        return null;
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // لود تنبل/Lazy Load رابطه‌ها روی کاربر لاگین‌شده
        $user->load(['roles', 'permissions', 'schools', 'termEnrollments.schoolClass.academicLevel.academicField.school']);

        return $this->jsonResponseOk($user);
    }
}
