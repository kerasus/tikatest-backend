<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Models\User;
use App\Traits\Filter;
use App\Traits\CommonCRUD;
use App\Models\SchoolUser;
use Illuminate\Support\Str;
use App\Enums\UserRoleType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

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

    public function runDevScripts(Request $request): JsonResponse
    {
        // قفل دوم: حتی اگر روت اشتباهی بدون middleware صدا زده شد
        abort_unless($request->user()?->hasRole(UserRoleType::Admin->value), 403, 'Admins only.');

        // انتخاب اینکه کدوم اسکریپت اجرا بشه
        $action = (string) $request->input('action', 'fix-student-passwords');

//        if ($action === 'fix-student-passwords') {
//            return $this->fixStudentPasswordsForSchools([1, 2]); // 1=مبتکران؟ 2=اسدی کیا
//        }

        // اکشن جدید برای انتقال فایل‌های آزمون
        if ($action === 'migrate-exam-files') {
            $limit = (int) $request->input('limit', 0); // می‌توانی اول با limit=5 تست کنی
            return $this->migrateExternalExamFiles($limit);
        }


        return response()->json([
            'message' => 'Unknown action',
            'action' => $action,
        ], 422);
    }

    private function fixStudentPasswordsForSchools(array $schoolIds): JsonResponse
    {
        $studentRoleId = 4; // اگر در سیستم تو متفاوت است اصلاح کن

        // model_type استاندارد اسپتی
        $modelType = 'App\\Models\\User';

        $fixed = 0;
        $skippedAlreadyBcrypt = 0;
        $skippedEmptyPassword = 0;

        DB::beginTransaction();
        try {
            // کاربرانی که دانش‌آموز هستند و در یکی از مدارس موردنظر ثبت‌نام دارند
            $users = DB::table('users as u')
                ->selectRaw('DISTINCT u.id, u.password')
                ->join('model_has_roles as mhr', function ($j) use ($modelType, $studentRoleId) {
                    $j->on('mhr.model_id', '=', 'u.id')
                        ->where('mhr.model_type', '=', $modelType)
                        ->where('mhr.role_id', '=', $studentRoleId);
                })
                ->join('term_enrollments as te', 'te.user_id', '=', 'u.id')
                ->whereIn('te.school_id', $schoolIds)
                ->lockForUpdate()
                ->get();

            foreach ($users as $u) {
                $current = (string) ($u->password ?? '');

                if ($current === '') {
                    $skippedEmptyPassword++;
                    continue;
                }

                // اگر از قبل bcrypt است دست نزن
                if (preg_match('/^\$2[aby]\$\d{2}\$[A-Za-z0-9\.\/]{53}$/', $current)) {
                    $skippedAlreadyBcrypt++;
                    continue;
                }

                // همین پسورد خام فعلی را bcrypt کن
                $newHash = bcrypt($current);

                DB::table('users')->where('id', $u->id)->update([
                    'password' => $newHash,
                    'updated_at' => now(),
                ]);

                $fixed++;
            }

            DB::commit();

            return response()->json([
                'message' => 'Student passwords fixed (bcrypt applied where needed).',
                'schools' => $schoolIds,
                'fixed' => $fixed,
                'skipped_already_bcrypt' => $skippedAlreadyBcrypt,
                'skipped_empty_password' => $skippedEmptyPassword,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * دانلود فایل‌های خارجی content و solution در online_exam_details
     * و ذخیره‌سازی آن‌ها دقیقاً با الگوی استاندارد ExamService
     */
    private function migrateExternalExamFiles(int $limit = 0): JsonResponse
    {
        @ini_set('max_execution_time', '600');
        @set_time_limit(600);

        $disk = Storage::disk('public');
        $targetDirectory = 'exam-files';

        if (! $disk->exists($targetDirectory)) {
            $disk->makeDirectory($targetDirectory);
        }

        $query = DB::table('online_exam_details')
            ->select('id', 'content', 'solution')
            ->where(function ($q) {
                $q->where('content', 'like', '%http://%')
                    ->orWhere('content', 'like', '%https://%')
                    ->orWhere('solution', 'like', '%http://%')
                    ->orWhere('solution', 'like', '%https://%');
            });

        if ($limit > 0) {
            $query->limit($limit);
        }

        $records = $query->get();

        $updatedCount = 0;
        $downloadedFilesCount = 0;
        $failedUrls = [];

        foreach ($records as $record) {
            $needsUpdate = false;
            $updates = [];

            // ۱. پردازش Content
            $contentData = $this->decodeJsonField($record->content);
            if ($contentData && isset($contentData['path']) && $this->isExternalUrl($contentData['path'])) {
                $newPath = $this->downloadAndStoreExamFile(
                    $contentData['path'],
                    'content',
                    $disk,
                    $targetDirectory,
                    $failedUrls
                );

                if ($newPath) {
                    $contentData['path'] = $newPath;
                    // اگر type نداشت، بر اساس پسوند ست کن
                    if (empty($contentData['type'])) {
                        $contentData['type'] = Str::endsWith(strtolower($newPath), '.pdf') ? 'pdf' : 'image';
                    }

                    $updates['content'] = json_encode($contentData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $needsUpdate = true;
                    $downloadedFilesCount++;
                }
            }

            // ۲. پردازش Solution
            $solutionData = $this->decodeJsonField($record->solution);
            if ($solutionData && isset($solutionData['path']) && $this->isExternalUrl($solutionData['path'])) {
                $newPath = $this->downloadAndStoreExamFile(
                    $solutionData['path'],
                    'solution',
                    $disk,
                    $targetDirectory,
                    $failedUrls
                );

                if ($newPath) {
                    $solutionData['path'] = $newPath;
                    if (empty($solutionData['type'])) {
                        $solutionData['type'] = Str::endsWith(strtolower($newPath), '.pdf') ? 'pdf' : 'image';
                    }

                    $updates['solution'] = json_encode($solutionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $needsUpdate = true;
                    $downloadedFilesCount++;
                }
            }

            if ($needsUpdate) {
                $updates['updated_at'] = now();
                DB::table('online_exam_details')
                    ->where('id', $record->id)
                    ->update($updates);

                $updatedCount++;
            }
        }

        return response()->json([
            'message' => 'فایل‌های خارجی با الگوی استاندارد ExamService با موفقیت منتقل شدند.',
            'total_matched_records' => $records->count(),
            'updated_records' => $updatedCount,
            'downloaded_files' => $downloadedFilesCount,
            'failed_count' => count($failedUrls),
            'failed_urls' => $failedUrls,
        ]);
    }

    /**
     * دانلود و نام‌گذاری دقیقاً مشابه متد storeExamFile در ExamService
     * فرمت خروجی: exam-files/exam_{prefix}_{uniqid}.{extension}
     */
    private function downloadAndStoreExamFile(string $url, string $prefix, $disk, string $directory, array &$failedUrls): ?string
    {
        try {
            $parsedUrlPath = parse_url($url, PHP_URL_PATH);
            $extension = strtolower(pathinfo($parsedUrlPath, PATHINFO_EXTENSION));

            if (empty($extension)) {
                $extension = 'pdf'; // پیش‌فرض رایج آزمون‌ها
            }

            // دانلود فایل
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->get($url);

            if (! $response->successful()) {
                $failedUrls[] = [
                    'url' => $url,
                    'status' => $response->status(),
                    'reason' => 'دانلود ناموفق بود.',
                ];
                return null;
            }

            // نام‌گذاری کاملاً مطابق storeExamFile در ExamService:
            // exam_{content|solution}_{uniqid}.{ext}
            $filename = sprintf('exam_%s_%s.%s', $prefix, uniqid(), $extension);
            $relativePath = "{$directory}/{$filename}";

            $disk->put($relativePath, $response->body());

            return $relativePath;
        } catch (\Throwable $e) {
            $failedUrls[] = [
                'url' => $url,
                'reason' => $e->getMessage(),
            ];
            return null;
        }
    }

    private function isExternalUrl(?string $path): bool
    {
        if (! $path) {
            return false;
        }
        return Str::startsWith($path, ['http://', 'https://']);
    }

    private function decodeJsonField($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

}
