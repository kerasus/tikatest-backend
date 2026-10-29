<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageOwner;
use App\Models\User;
use App\Services\Notification\NotificationManager;
use App\Services\Notification\NotificationMessage;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:messages.view')->only(['index', 'show', 'sent', 'received', 'myMessages']);
        $this->middleware('admin_or_permission:messages.create')->only(['store', 'send', 'sendToClass', 'sendToStudent']);
        $this->middleware('admin_or_permission:messages.update')->only(['update']);
        $this->middleware('admin_or_permission:messages.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['subject'],
            'filterDate' => ['sent_at', 'created_at'],
            'filterRelationIds' => [
                [
                    'requestKey' => 'sender_ids',
                    'relationName' => 'sender',
                ],
            ],
            'eagerLoads' => ['school', 'sender', 'owners.user'],
        ];

        return $this->commonIndex($request, Message::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'sender_id' => 'nullable|exists:users,id',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'attachment' => 'nullable|string|max:255',
            'is_sms' => 'boolean',
            'message_type' => 'nullable|string|max:50',
            'sent_at' => 'nullable|date',
            'receiver_ids' => 'required|array',
            'receiver_ids.*' => 'exists:users,id',
            'recipient_types' => 'required|array',
            'recipient_types.*.user_id' => 'required|exists:users,id',
            'recipient_types.*.is_student' => 'boolean',
            'recipient_types.*.is_father' => 'boolean',
            'recipient_types.*.is_mother' => 'boolean',
        ]);

        DB::beginTransaction();

        try {
            $message = Message::create([
                'school_id' => $request->input('school_id'),
                'sender_id' => $request->sender_id ?? auth()->id(),
                'subject' => $request->subject,
                'body' => $request->body,
                'attachment' => $request->attachment,
                'is_sms' => $request->is_sms ?? false,
                'message_type' => $request->message_type,
                'sent_at' => $request->sent_at ?? now(),
            ]);

            foreach ($request->recipient_types as $recipient) {
                MessageOwner::create([
                    'message_id' => $message->id,
                    'user_id' => $recipient['user_id'],
                    'is_student' => $recipient['is_student'] ?? false,
                    'is_father' => $recipient['is_father'] ?? false,
                    'is_mother' => $recipient['is_mother'] ?? false,
                ]);
            }

            DB::commit();

            return $this->jsonResponseOk($message->load(['sender', 'owners.user']));
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->jsonResponseServerError(['errors' => ['message' => 'خطا در ارسال پیام.']]);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        $message = Message::with(['school', 'sender', 'owners.user'])->findOrFail($id);

        return $this->jsonResponseOk($message);
    }

    public function destroy(Message $message): JsonResponse
    {
        return $this->commonDestroy($message);
    }

    public function sent(Request $request): JsonResponse
    {
        $messages = Message::where('sender_id', auth()->id())
            ->with(['owners.user'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->jsonResponseOk($messages);
    }

    public function received(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $messages = Message::whereHas('owners', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
            ->with(['sender', 'owners'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->jsonResponseOk($messages);
    }

    public function myMessages(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $messages = Message::where(function ($query) use ($userId) {
            $query->where('sender_id', $userId)
                ->orWhereHas('owners', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
        })
            ->with(['sender', 'owners'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->jsonResponseOk($messages);
    }

    public function sendToStudent(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'attachment' => 'nullable|string|max:255',
            'is_sms' => 'boolean',
            'message_type' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();

        try {
            $message = Message::create([
                'school_id' => $request->input('school_id'),
                'sender_id' => auth()->id(),
                'subject' => $request->subject,
                'body' => $request->body,
                'attachment' => $request->attachment,
                'is_sms' => $request->is_sms ?? false,
                'message_type' => $request->message_type ?? 'inner',
                'sent_at' => now(),
            ]);

            MessageOwner::create([
                'message_id' => $message->id,
                'user_id' => $request->student_id,
                'is_student' => true,
                'is_father' => false,
                'is_mother' => false,
            ]);

            DB::commit();

            return $this->jsonResponseOk($message->load(['sender', 'owners.user']));
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->jsonResponseServerError(['errors' => ['message' => 'خطا در ارسال پیام.']]);
        }
    }

    public function sendToClass(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'attachment' => 'nullable|string|max:255',
            'is_sms' => 'boolean',
            'message_type' => 'nullable|string|max:50',
            'recipient_types' => 'required|array',
            'recipient_types.*' => 'string|in:student,father,mother',
        ]);

        $recipientTypes = $request->recipient_types;

        DB::beginTransaction();

        try {
            $message = Message::create([
                'school_id' => $request->input('school_id'),
                'sender_id' => auth()->id(),
                'subject' => $request->subject,
                'body' => $request->body,
                'attachment' => $request->attachment,
                'is_sms' => $request->is_sms ?? false,
                'message_type' => $request->message_type ?? 'inner',
                'sent_at' => now(),
            ]);

            $students = User::whereHas('studentClassRegistrations', function ($query) use ($request) {
                $query->where('class_id', $request->class_id);
            })->get();

            foreach ($students as $student) {
                $isStudent = in_array('student', $recipientTypes);
                $isFather = in_array('father', $recipientTypes);
                $isMother = in_array('mother', $recipientTypes);

                MessageOwner::create([
                    'message_id' => $message->id,
                    'user_id' => $student->id,
                    'is_student' => $isStudent,
                    'is_father' => $isFather,
                    'is_mother' => $isMother,
                ]);
            }

            DB::commit();

            return $this->jsonResponseOk($message->load(['sender', 'owners.user']));
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->jsonResponseServerError(['errors' => ['message' => 'خطا در ارسال پیام به کلاس.']]);
        }
    }

    public function sendToStudents(Request $request): JsonResponse
    {
        $request->validate([
            'body' => 'required|string',
            'school_id' => 'nullable|integer|exists:schools,id',
            'field_id' => 'nullable|integer|exists:academic_fields,id',
            'academic_level_id' => 'nullable|integer|exists:academic_levels,id',
            'class_id' => 'nullable|integer|exists:classes,id',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|max:20',
            'username' => 'nullable|string|max:255',
        ]);

        $filterKeys = ['school_id', 'field_id', 'academic_level_id', 'class_id', 'first_name', 'last_name', 'national_id', 'username'];
        if (! collect($filterKeys)->contains(fn ($key) => $request->filled($key))) {
            throw ValidationException::withMessages(['filters' => 'حداقل یک فیلتر برای انتخاب دانش‌آموزان الزامی است.']);
        }

        $modelQuery = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'student'));

        foreach (['first_name', 'last_name', 'national_id', 'username'] as $key) {
            if ($request->filled($key)) {
                $modelQuery->where($key, 'like', '%'.trim($request->input($key)).'%');
            }
        }

        if ($request->filled('school_id') || $request->filled('field_id') || $request->filled('academic_level_id') || $request->filled('class_id')) {
            $modelQuery->whereHas('termEnrollments', function ($registrationQuery) use ($request) {
                if ($request->filled('class_id')) {
                    $registrationQuery->where('term_enrollments.class_id', $request->integer('class_id'));
                }
                $registrationQuery->whereHas('schoolClass', function ($classQuery) use ($request) {
                    if ($request->filled('academic_level_id')) {
                        $classQuery->where('classes.academic_level_id', $request->integer('academic_level_id'));
                    }
                    if ($request->filled('field_id') || $request->filled('school_id')) {
                        $classQuery->whereHas('academicLevel', function ($levelQuery) use ($request) {
                            if ($request->filled('field_id')) {
                                $levelQuery->where('academic_levels.field_id', $request->integer('field_id'));
                            }
                            if ($request->filled('school_id')) {
                                $levelQuery->whereHas('academicField', fn ($f) => $f->where('academic_fields.school_id', $request->integer('school_id')));
                            }
                        });
                    }
                });
            });
        }

        $students = $modelQuery->whereNotNull('bale_chat_id')->get();
        $message = NotificationMessage::make('پیام', $request->body);
        $sent = 0;

        foreach ($students as $student) {
            try {
                $result = app(NotificationManager::class)->sendVia('bale', $student, $message);
                if ($result['ok'] ?? false) {
                    $sent++;
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $this->jsonResponseOk(['sent' => $sent]);
    }

    public function markAsRead(Request $request, MessageOwner $messageOwner): JsonResponse
    {
        $messageOwner->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $this->jsonResponseOk($messageOwner);
    }
}
