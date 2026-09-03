<?php

use App\Http\Controllers\Api\AcademicFieldController;
use App\Http\Controllers\Api\AcademicLevelController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DisciplinaryCaseController;
use App\Http\Controllers\Api\DisciplinaryRecordController;
use App\Http\Controllers\Api\ExamCategoryController;
use App\Http\Controllers\Api\ExamCategoryTermLimitController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\TermController;
use App\Http\Controllers\Api\TermEnrollmentController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\HomeworkController;
use App\Http\Controllers\Api\HomeworkSubmissionController;
use App\Http\Controllers\Api\InPersonExamDetailController;
use App\Http\Controllers\Api\InPersonExamResultController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OnlineExamAnswerKeyController;
use App\Http\Controllers\Api\OnlineExamBookletController;
use App\Http\Controllers\Api\OnlineExamDetailController;
use App\Http\Controllers\Api\OnlineExamSessionController;
use App\Http\Controllers\Api\OnlineExamSessionResponseController;
use App\Http\Controllers\Api\SchoolClassController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('users/{user}/assign-role', [UserController::class, 'assignRole']);
    Route::post('users/{user}/remove-role', [UserController::class, 'removeRole']);
    Route::get('users/role/{role}', [UserController::class, 'getByRole']);
    Route::apiResource('users', UserController::class);

    Route::apiResource('schools', SchoolController::class);

    Route::prefix('schools/{school}')->group(function () {
        Route::get('terms', [SchoolController::class, 'termsIndex']);
        Route::post('terms', [SchoolController::class, 'termsStore']);
        Route::get('terms/{term}', [SchoolController::class, 'termsShow']);
        Route::put('terms/{term}', [SchoolController::class, 'termsUpdate']);
        Route::delete('terms/{term}', [SchoolController::class, 'termsDestroy']);
    });
    Route::apiResource('academic-fields', AcademicFieldController::class);
    Route::apiResource('academic-levels', AcademicLevelController::class);
    Route::apiResource('classes', SchoolClassController::class);
    Route::apiResource('students', StudentController::class);
    Route::apiResource('student-profiles', StudentProfileController::class);
    Route::apiResource('student-guardians', StudentGuardianController::class);
    Route::apiResource('lessons', LessonController::class);
    Route::apiResource('exams', ExamController::class);
    Route::post('exams/store-with-online-detail', [ExamController::class, 'storeWithOnlineDetail']);
    Route::post('exams/update-with-online-detail/{exam}', [ExamController::class, 'updateWithOnlineDetail']);
    Route::post('exams/store-with-inperson-results', [ExamController::class, 'storeWithInPersonDetailAndResults']);
    Route::get('exams/{exam}/students', [ExamController::class, 'examStudents']);
    Route::apiResource('exam-categories', ExamCategoryController::class);
    Route::apiResource('academic-terms', TermController::class);
    Route::apiResource('exam-category-term-limits', ExamCategoryTermLimitController::class);
    Route::apiResource('in-person-exam-details', InPersonExamDetailController::class);
    Route::apiResource('in-person-exam-results', InPersonExamResultController::class);
    Route::apiResource('online-exam-details', OnlineExamDetailController::class);
    Route::apiResource('online-exam-sessions', OnlineExamSessionController::class);
    Route::apiResource('online-exam-session-responses', OnlineExamSessionResponseController::class);
    Route::apiResource('online-exam-answer-keys', OnlineExamAnswerKeyController::class);
    Route::apiResource('online-exam-booklets', OnlineExamBookletController::class);
    Route::apiResource('grades', GradeController::class);
    Route::post('grades/bulk', [GradeController::class, 'bulkStore']);
    Route::post('grades/store-with-exam', [GradeController::class, 'createExamWithGrades']);
    Route::get('grades/statistics/{lesson_id}/{class_id}', [GradeController::class, 'statistics']);
    Route::post('grades/update-z-scores', [GradeController::class, 'updateZScores']);
    Route::get('online-exam-details/{id}/results-with-rank', [OnlineExamDetailController::class, 'resultsWithRank']);

    Route::prefix('online-exam-sessions')->group(function () {
        Route::get('my-sessions', [OnlineExamSessionController::class, 'mySessions']);
        Route::post('auto-expire', [OnlineExamSessionController::class, 'autoExpire']);
        Route::post('{examId}/start', [OnlineExamSessionController::class, 'startSession']);
        Route::get('{examId}/sessions', [OnlineExamSessionController::class, 'getExamSessions']);
        Route::get('{examId}/result', [OnlineExamSessionController::class, 'getResultByExamId']);
        Route::get('{sessionId}/view', [OnlineExamSessionController::class, 'getSession']);
        Route::post('{sessionId}/answer', [OnlineExamSessionController::class, 'submitAnswer']);
        Route::post('{sessionId}/submit', [OnlineExamSessionController::class, 'submitSession']);
    });

    Route::apiResource('disciplinary-cases', DisciplinaryCaseController::class);
    Route::apiResource('disciplinary-records', DisciplinaryRecordController::class);
    Route::post('disciplinary/absenteeism', [DisciplinaryRecordController::class, 'registerAbsenteeism']);
    Route::get('disciplinary/absences', [DisciplinaryRecordController::class, 'viewAbsences']);
    Route::get('homework/mine', [HomeworkController::class, 'myHomework']);
    Route::get('homework/{homeworkId}/view', [HomeworkController::class, 'viewHomework']);
    Route::apiResource('homework', HomeworkController::class);
    Route::post('homework/{homeworkId}/submit', [HomeworkController::class, 'submitHomework']);
    Route::post('homework/{homeworkId}/attachments', [HomeworkController::class, 'storeAttachments']);
    Route::delete('homework/{homeworkId}/attachments/{attachmentId}', [HomeworkController::class, 'destroyAttachment']);
    Route::apiResource('homework-submissions', HomeworkSubmissionController::class);
    Route::put('homework-submissions/{homeworkSubmission}/seen', [HomeworkSubmissionController::class, 'markAsSeen']);
    Route::put('homework-submissions/{homeworkSubmission}/feedback', [HomeworkSubmissionController::class, 'sendFeedback']);
    Route::apiResource('messages', MessageController::class);
    Route::get('messages/sent', [MessageController::class, 'sent']);
    Route::get('messages/received', [MessageController::class, 'received']);
    Route::post('messages/send-to-student', [MessageController::class, 'sendToStudent']);
    Route::post('messages/send-to-class', [MessageController::class, 'sendToClass']);
    Route::patch('messages/{message}/read', [MessageController::class, 'markAsRead']);
    Route::get('grades/report/lesson/{lessonId}', [GradeController::class, 'lessonReport']);
    Route::get('grades/report/multiple-lessons', [GradeController::class, 'multipleLessonsReport']);
    Route::get('grades/report/student/{studentId}', [GradeController::class, 'studentReport']);
    Route::get('grades/report/student/{studentId}/report-card', [GradeController::class, 'getStudentReportCard']);
    Route::get('study-sessions/report/general', [StudentController::class, 'studyHoursGeneralReport']);
    Route::get('study-sessions/report/student/{studentId}', [StudentController::class, 'studyHoursStudentReport']);
    Route::apiResource('term-enrollments', TermEnrollmentController::class)->except(['update']);

    Route::prefix('student-portal')->group(function () {
        Route::get('grades', [StudentController::class, 'myGrades']);
        Route::get('report-card', [StudentController::class, 'myReportCard']);
        Route::get('absences', [StudentController::class, 'myAbsences']);
        Route::get('disciplinary', [StudentController::class, 'myDisciplinaryRecords']);
        Route::get('messages', [MessageController::class, 'myMessages']);
        Route::post('messages/send', [MessageController::class, 'sendToStudent']);
        Route::get('messages/sent', [MessageController::class, 'sent']);
        Route::get('messages/received', [MessageController::class, 'received']);
        Route::get('study-sessions', [StudentController::class, 'studySessions']);
        Route::post('study-sessions', [StudentController::class, 'storeStudySession']);
        Route::get('study-sessions/{id}', [StudentController::class, 'showStudySession']);
        Route::put('study-sessions/{id}', [StudentController::class, 'updateStudySession']);
        Route::delete('study-sessions/{id}', [StudentController::class, 'destroyStudySession']);
        Route::get('homework/my-submissions', [HomeworkController::class, 'mySubmissions']);
        Route::get('my-exams', [ExamController::class, 'myExams']);
        Route::get('homework-submissions', [HomeworkSubmissionController::class, 'index']);
        Route::put('homework-submissions/{homeworkSubmission}/feedback', [HomeworkSubmissionController::class, 'sendFeedback']);
        Route::get('online-exam-sessions', [OnlineExamSessionController::class, 'mySessions']);
        Route::get('online-exams/{examId}/result', [OnlineExamSessionController::class, 'getResultByExamId']);
        Route::get('online-exams', [ExamController::class, 'studentOnlineExams']);
        Route::get('dashboard', [StudentController::class, 'dashboard']);
    });

    Route::prefix('exam-management')->group(function () {
        Route::post('online-exam-sessions/auto-expire', [OnlineExamSessionController::class, 'autoExpire']);
    });
});
