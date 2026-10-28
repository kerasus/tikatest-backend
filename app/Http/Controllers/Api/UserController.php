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
use App\Services\SchoolFtpFileService;
use Illuminate\Support\Facades\Artisan;
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
                    'password' => 'required|string',
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

        if ($action === 'fix-student-passwords') {
            $schoolIds = $request->input('school_ids', [1, 2]);
            if (!is_array($schoolIds)) {
                $schoolIds = explode(',', (string) $schoolIds);
            }
            $schoolIds = array_filter(array_map('intval', (array) $schoolIds));

            return $this->fixStudentPasswordsForSchools($schoolIds ?: [1, 2]);
        }

        // اکشن جدید برای انتقال فایل‌های آزمون
        if ($action === 'migrate-exam-files') {
            $limit = (int) $request->input('limit', 0); // می‌توانی اول با limit=5 تست کنی
            return $this->migrateExternalExamFiles($limit);
        }

        // اکشن بازتولید و تصحیح نتایج جلسات و شمارش سوالات
        if ($action === 'recalculate-exam-results') {
            $examId = $request->input('exam_id') ? (int) $request->input('exam_id') : null;
            return $this->recalculateExamSessionResults($examId);
        }

        // اکشن تنظیم وب‌هوک بله
        if ($action === 'set-bale-webhook') {
            $exitCode = Artisan::call('bale:set-webhook');
            $output = Artisan::output();

            return response()->json([
                'success' => $exitCode === 0,
                'exit_code' => $exitCode,
                'output' => trim($output),
            ], $exitCode === 0 ? 200 : 500);
        }

        if ($action === 'flush-school-ftp-cache') {
            $validated = $request->validate([
                'school_id' => ['nullable', 'integer', 'min:1', 'exists:schools,id'],
            ]);
            $schoolId = isset($validated['school_id']) ? (int) $validated['school_id'] : null;
            $success = app(SchoolFtpFileService::class)->flushAllServiceCache($schoolId);

            return response()->json([
                'success' => $success,
                'message' => $success ? 'کش فایل‌های FTP پاک شد.' : 'پاک‌سازی کش فایل‌های FTP ناموفق بود.',
                'school_id' => $schoolId,
            ], $success ? 200 : 500);
        }

        return response()->json([
            'message' => 'Unknown action',
            'action' => $action,
        ], 422);
    }

    /**
     * بازشماری و تصحیح تمام پاسخ‌ها، نتایج، تعداد درست/غلط/بی‌پاسخ و درصدها
     */
    protected function recalculateExamSessionResults(?int $examId = null): JsonResponse
    {
        $sessionsQuery = \DB::table('online_exam_sessions');
        if ($examId) {
            $sessionsQuery->where('exam_id', $examId);
        }

        $sessions = $sessionsQuery->get();

        $processedSessions = 0;
        $updatedResponsesCount = 0;

        foreach ($sessions as $session) {
            // ۱. دریافت کلیدهای آزمون
            $answerKeys = \DB::table('online_exam_answer_keys')
                ->where('exam_id', $session->exam_id)
                ->where('is_active', 1)
                ->get()
                ->keyBy('question_number');

            if ($answerKeys->isEmpty()) {
                continue;
            }

            // ۲. دریافت پاسخ‌های ثبت‌شده دانش‌آموز برای این سشن
            $responses = \DB::table('online_exam_session_responses')
                ->where('online_exam_session_id', $session->id)
                ->get()
                ->keyBy('question_number');

            // ۳. دفترچه‌ها (در صورت وجود)
            $booklets = \DB::table('online_exam_booklets')
                ->where('online_exam_id', function ($q) use ($session) {
                    $q->select('id')
                        ->from('online_exam_details')
                        ->where('exam_id', $session->exam_id)
                        ->limit(1);
                })
                ->get();

            // متغیرهای شمارش در سطح آزمون (کل)
            $examCorrect = 0;
            $examWrong = 0;
            $examUnanswered = 0;
            $examRawScore = 0.0;
            $examMaxScore = 0.0;

            // به روزرسانی آنلاین پاسخ‌های خام (is_correct و marks_obtained)
            foreach ($answerKeys as $qNum => $key) {
                $resp = $responses->get($qNum);
                $submitted = $resp ? trim((string)$resp->submitted_option) : '';
                $correctOpt = trim((string)$key->correct_option);
                $weight = (float)$key->weight;
                $choices = (int)$key->number_of_choices;
                $hasNegative = (bool)$key->has_negative_mark;

                $examMaxScore += $weight;

                $isCorrect = false;
                $marks = 0.0;

                // سوال بی‌پاسخ (خالی، null یا عددی خارج از گزینه‌ها)
                if ($submitted === '' || (int)$submitted === 0 || (int)$submitted > $choices) {
                    $examUnanswered++;
                    $isCorrect = null; // یا false
                    $marks = 0.0;
                } elseif ($submitted === $correctOpt) {
                    $examCorrect++;
                    $isCorrect = true;
                    $marks = $weight;
                    $examRawScore += $weight;
                } else {
                    $examWrong++;
                    $isCorrect = false;
                    // محاسبه نمره منفی در صورت فعال بودن
                    if ($hasNegative && $choices > 1) {
                        $penalty = $weight / ($choices - 1);
                        $marks = -$penalty;
                        $examRawScore -= $penalty;
                    } else {
                        $marks = 0.0;
                    }
                }

                // بروزرسانی ردیف پاسخ اگر وجود دارد
                if ($resp) {
                    \DB::table('online_exam_session_responses')
                        ->where('id', $resp->id)
                        ->update([
                            'is_correct' => $isCorrect === true ? 1 : 0,
                            'marks_obtained' => $marks,
                            'updated_at' => now(),
                        ]);
                    $updatedResponsesCount++;
                }
            }

            $examTotalQuestions = $answerKeys->count();
            $examAnswered = $examCorrect + $examWrong;

            // درصد نهایی (جلوگیری از تقسیم بر صفر)
            $calculatedPercent = $examMaxScore > 0
                ? round(max(0, ($examRawScore / $examMaxScore) * 100), 2)
                : 0.0;

            // اگر سشن از قبل درصد دستی داشت (از مپینگ قدیمی) یا بر اساس پاسخ‌ها آپدیت شود:
            $finalPercent = $session->percent !== null ? (float)$session->percent : $calculatedPercent;

            // ۴. بروزرسانی یا ثبت ردیف نتیجه سطح آزمون (scope = 'exam')
            \DB::table('online_exam_session_results')->updateOrInsert(
                [
                    'online_exam_session_id' => $session->id,
                    'scope' => 'exam',
                ],
                [
                    'exam_id' => $session->exam_id,
                    'student_id' => $session->student_id,
                    'online_exam_booklet_id' => null,
                    'lesson_id' => null,
                    'lesson_title' => null,
                    'raw_score' => max(0, $examRawScore),
                    'max_score' => $examMaxScore,
                    'percent' => $finalPercent,
                    'question_count' => $examTotalQuestions,
                    'answered_count' => $examAnswered,
                    'correct_count' => $examCorrect,
                    'wrong_count' => $examWrong,
                    'unanswered_count' => $examUnanswered,
                    'updated_at' => now(),
                ]
            );

            // ۵. بروزرسانی سطح دفترچه‌ها (scope = 'booklet')
            foreach ($booklets as $b) {
                $bCorrect = 0;
                $bWrong = 0;
                $bUnanswered = 0;
                $bRawScore = 0.0;
                $bMaxScore = 0.0;
                $bTotal = 0;

                for ($qn = $b->from_question; $qn <= $b->to_question; $qn++) {
                    if (!$answerKeys->has($qn)) {
                        continue;
                    }
                    $bTotal++;
                    $key = $answerKeys->get($qn);
                    $resp = $responses->get($qn);
                    $submitted = $resp ? trim((string)$resp->submitted_option) : '';
                    $correctOpt = trim((string)$key->correct_option);
                    $weight = (float)$key->weight;
                    $choices = (int)$key->number_of_choices;
                    $hasNegative = (bool)$key->has_negative_mark;

                    $bMaxScore += $weight;

                    if ($submitted === '' || (int)$submitted === 0 || (int)$submitted > $choices) {
                        $bUnanswered++;
                    } elseif ($submitted === $correctOpt) {
                        $bCorrect++;
                        $bRawScore += $weight;
                    } else {
                        $bWrong++;
                        if ($hasNegative && $choices > 1) {
                            $bRawScore -= ($weight / ($choices - 1));
                        }
                    }
                }

                $bPercent = $bMaxScore > 0 ? round(max(0, ($bRawScore / $bMaxScore) * 100), 2) : 0.0;

                \DB::table('online_exam_session_results')->updateOrInsert(
                    [
                        'online_exam_session_id' => $session->id,
                        'online_exam_booklet_id' => $b->id,
                        'scope' => 'booklet',
                    ],
                    [
                        'exam_id' => $session->exam_id,
                        'student_id' => $session->student_id,
                        'lesson_id' => $b->lesson_id,
                        'lesson_title' => $b->title,
                        'raw_score' => max(0, $bRawScore),
                        'max_score' => $bMaxScore,
                        'percent' => $bPercent,
                        'question_count' => $bTotal,
                        'answered_count' => ($bCorrect + $bWrong),
                        'correct_count' => $bCorrect,
                        'wrong_count' => $bWrong,
                        'unanswered_count' => $bUnanswered,
                        'updated_at' => now(),
                    ]
                );
            }

            // ۶. آپدیت سشن با درصد تجمیعی
            \DB::table('online_exam_sessions')
                ->where('id', $session->id)
                ->update([
                    'percent' => $finalPercent,
                    'updated_at' => now(),
                ]);

            $processedSessions++;
        }

        return response()->json([
            'message' => 'نتایج و پاسخ‌های تمام جلسات آزمون با موفقیت بازشماری و همگام شدند.',
            'sessions_processed' => $processedSessions,
            'responses_updated' => $updatedResponsesCount,
        ]);
    }


    public function runOptimize()
    {
        // برای امنیت، می‌تونی چک کنی کاربر ادمین باشه یا توکن داشته باشه
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        Artisan::call('event:cache');

        return response()->json([
            'message' => 'Application optimized successfully!',
            'output' => Artisan::output(),
        ]);
    }

    private function fixStudentPasswordsForSchools(array $schoolIds): JsonResponse
    {
        $studentRoleId = 4; // رول استیودنت در سیستم تیکاتست
        $modelType = 'App\\Models\\User';

        $fixed = 0;
        $skippedAlreadyBcrypt = 0;
        $skippedEmptyPassword = 0;

        try {
            // حذف DISTINCT برای جلوگیری از خطای 1221 در MySQL
            // استفاده از whereIn تمیزتره و هیچ ردیف تکراری ایجاد نمی‌کنه
            $users = DB::table('users as u')
                ->select('u.id', 'u.password')
                ->join('model_has_roles as mhr', function ($j) use ($modelType, $studentRoleId) {
                    $j->on('mhr.model_id', '=', 'u.id')
                        ->where('mhr.model_type', '=', $modelType)
                        ->where('mhr.role_id', '=', $studentRoleId);
                })
                ->whereIn('u.id', function ($query) use ($schoolIds) {
                    $query->select('user_id')
                        ->from('term_enrollments')
                        ->whereIn('school_id', $schoolIds);
                })
                ->get();

            // ترنزکشن را داخل پردازش می‌بریم یا برای کل آپدیت‌ها نگه می‌داریم بدون lockForUpdate
            DB::beginTransaction();

            foreach ($users as $u) {
                $current = (string) ($u->password ?? '');

                if ($current === '') {
                    $skippedEmptyPassword++;
                    continue;
                }

                // اگر پسورد قبلاً هش Bcrypt لاراولی بود رد شو
                if (preg_match('/^\$2[aby]\$\d{2}\$[A-Za-z0-9\.\/]{53}$/', $current)) {
                    $skippedAlreadyBcrypt++;
                    continue;
                }

                // هش کردن پسورد خام فعلی
                DB::table('users')
                    ->where('id', $u->id)
                    ->update([
                        'password' => bcrypt($current),
                        'updated_at' => now(),
                    ]);

                $fixed++;
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Student passwords fixed (bcrypt applied where needed).',
                'schools' => $schoolIds,
                'total_checked' => $users->count(),
                'fixed' => $fixed,
                'skipped_already_bcrypt' => $skippedAlreadyBcrypt,
                'skipped_empty_password' => $skippedEmptyPassword,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to fix passwords',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * دانلود فایل‌های خارجی content و solution در online_exam_details
     * و ذخیره‌سازی آن‌ها دقیقاً با الگوی استاندارد ExamService
     * داخل مسیر exam-files/{school-slug}
     */
    private function migrateExternalExamFiles(int $limit = 0): JsonResponse
    {
        @ini_set('max_execution_time', '600');
        @set_time_limit(600);

        $disk = Storage::disk('public');
        $baseDirectory = 'exam-files';

        if (! $disk->exists($baseDirectory)) {
            $disk->makeDirectory($baseDirectory);
        }

        $query = DB::table('online_exam_details as oed')
            ->join('exams as e', 'e.id', '=', 'oed.exam_id')
            ->leftJoin('academic_terms as at', 'at.id', '=', 'e.term_id')
            ->leftJoin('schools as s', 's.id', '=', 'at.school_id')
            ->select([
                'oed.id',
                'oed.exam_id',
                'oed.content',
                'oed.solution',
                'e.term_id',
                's.slug as school_slug',
            ])
            ->where(function ($q) {
                $q->where('oed.content', 'like', '%http://%')
                    ->orWhere('oed.content', 'like', '%https://%')
                    ->orWhere('oed.solution', 'like', '%http://%')
                    ->orWhere('oed.solution', 'like', '%https://%');
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

            $schoolSlug = $this->resolveSchoolSlugForMigration($record->school_slug);
            $targetDirectory = $baseDirectory . '/' . $schoolSlug;

            if (! $disk->exists($targetDirectory)) {
                $disk->makeDirectory($targetDirectory);
            }

            // 1) پردازش content
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

                    if (empty($contentData['type'])) {
                        $contentData['type'] = Str::endsWith(strtolower($newPath), '.pdf') ? 'pdf' : 'image';
                    }

                    $updates['content'] = json_encode($contentData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $needsUpdate = true;
                    $downloadedFilesCount++;
                }
            }

            // 2) پردازش solution
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
            'message' => 'فایل‌های خارجی با الگوی جدید ExamService و بر اساس slug مدرسه با موفقیت منتقل شدند.',
            'total_matched_records' => $records->count(),
            'updated_records' => $updatedCount,
            'downloaded_files' => $downloadedFilesCount,
            'failed_count' => count($failedUrls),
            'failed_urls' => $failedUrls,
        ]);
    }

    private function resolveSchoolSlugForMigration(?string $schoolSlug): string
    {
        return ! empty($schoolSlug)
            ? Str::slug($schoolSlug)
            : 'default-school';
    }

    /**
     * دانلود و نام‌گذاری دقیقاً مشابه متد storeExamFile در ExamService
     * فرمت خروجی: exam-files/{school-slug}/exam_{prefix}_{uniqid}.{extension}
     */
    private function downloadAndStoreExamFile(string $url, string $prefix, $disk, string $directory, array &$failedUrls): ?string
    {
        try {
            $parsedUrlPath = parse_url($url, PHP_URL_PATH);
            $extension = strtolower(pathinfo($parsedUrlPath, PATHINFO_EXTENSION));

            if (empty($extension)) {
                $extension = 'pdf';
            }

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
