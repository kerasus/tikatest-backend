<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\AcademicFieldController;
use App\Http\Controllers\Api\AcademicLevelController;
use App\Http\Controllers\Api\SchoolClassController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\ExamSessionController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\QuizClassAssignmentController;
use App\Http\Controllers\Api\QuizAttemptController;
use App\Http\Controllers\Api\QuizQuestionController;
use App\Http\Controllers\Api\QuizQuestionOptionController;
use App\Http\Controllers\Api\QuizSessionController;
use App\Http\Controllers\Api\DisciplinaryCaseController;
use App\Http\Controllers\Api\DisciplinaryRecordController;
use App\Http\Controllers\Api\HomeworkController;
use App\Http\Controllers\Api\HomeworkSubmissionController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PreRegistrationController;
use App\Http\Controllers\Api\StudentClassRegistrationController;
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
    Route::apiResource('users', UserController::class);

    Route::apiResource('tags', TagController::class);

    Route::post('places/import', [PlaceController::class, 'import']);
    Route::post('places/{place}/tags/sync', [PlaceController::class, 'syncTags']);
    Route::post('places/{place}/tags/attach', [PlaceController::class, 'attachTags']);
    Route::post('places/{place}/tags/detach', [PlaceController::class, 'detachTags']);
    Route::apiResource('places', PlaceController::class);

    Route::apiResource('schools', SchoolController::class);
    Route::apiResource('academic-fields', AcademicFieldController::class);
    Route::apiResource('academic-levels', AcademicLevelController::class);
    Route::apiResource('classes', SchoolClassController::class);
    Route::apiResource('students', StudentController::class);
    Route::apiResource('lessons', LessonController::class);
    Route::apiResource('exam-sessions', ExamSessionController::class);
    Route::apiResource('grades', GradeController::class);
    Route::post('grades/bulk', [GradeController::class, 'bulkStore']);
    Route::get('grades/statistics/{lesson_id}/{class_id}', [GradeController::class, 'statistics']);
    Route::post('grades/update-z-scores', [GradeController::class, 'updateZScores']);
    Route::get('quizzes/{quiz}/results-with-rank', [QuizController::class, 'resultsWithRank']);
    Route::post('quizzes/{quiz}/participants', [QuizController::class, 'assignParticipants']);
    Route::apiResource('quizzes', QuizController::class);
    Route::apiResource('quiz-assignments', QuizClassAssignmentController::class);
    Route::apiResource('quiz-attempts', QuizAttemptController::class);
    Route::apiResource('quiz-questions', QuizQuestionController::class);
    Route::apiResource('quiz-question-options', QuizQuestionOptionController::class);

    Route::prefix('quiz-sessions')->group(function () {
        Route::get('my-attempts', [QuizSessionController::class, 'myAttempts']);
        Route::post('auto-expire', [QuizSessionController::class, 'autoExpire']);
        Route::post('{quizId}/start', [QuizSessionController::class, 'startSession']);
        Route::get('{sessionId}', [QuizSessionController::class, 'getSession']);
        Route::post('{sessionId}/answer', [QuizSessionController::class, 'submitAnswer']);
        Route::post('{sessionId}/submit', [QuizSessionController::class, 'submitSession']);
        Route::post('{sessionId}/anti-cheat', [QuizSessionController::class, 'reportAntiCheatEvent']);
        Route::get('{sessionId}/anti-cheat-events', [QuizSessionController::class, 'antiCheatEvents']);
    });

    Route::apiResource('disciplinary-cases', DisciplinaryCaseController::class);
    Route::apiResource('disciplinary-records', DisciplinaryRecordController::class);
    Route::post('disciplinary/absenteeism', [DisciplinaryRecordController::class, 'registerAbsenteeism']);
    Route::get('disciplinary/absences', [DisciplinaryRecordController::class, 'viewAbsences']);
    Route::apiResource('homework', HomeworkController::class);
    Route::apiResource('homework-submissions', HomeworkSubmissionController::class);
    Route::apiResource('messages', MessageController::class);
    Route::get('messages/sent', [MessageController::class, 'sentMessages']);
    Route::get('messages/received', [MessageController::class, 'receivedMessages']);
    Route::get('grades/report/lesson/{lessonId}', [GradeController::class, 'lessonReport']);
    Route::get('grades/report/multiple-lessons', [GradeController::class, 'multipleLessonsReport']);
    Route::get('grades/report/student/{studentId}', [GradeController::class, 'studentReport']);
    Route::get('study-sessions/report/general', [StudentController::class, 'studyHoursGeneralReport']);
    Route::get('study-sessions/report/student/{studentId}', [StudentController::class, 'studyHoursStudentReport']);
    Route::apiResource('pre-registrations', PreRegistrationController::class)->only(['index', 'store']);
    Route::apiResource('student-class-registrations', StudentClassRegistrationController::class)->except(['update']);

    Route::prefix('student-portal')->group(function () {
        Route::get('grades', [StudentController::class, 'myGrades']);
        Route::get('report-card', [StudentController::class, 'myReportCard']);
        Route::get('absences', [StudentController::class, 'myAbsences']);
        Route::get('disciplinary', [StudentController::class, 'myDisciplinaryRecords']);
        Route::get('messages', [MessageController::class, 'myMessages']);
        Route::post('messages', [MessageController::class, 'sendMessage']);
        Route::get('study-sessions', [StudentController::class, 'studySessions']);
        Route::post('study-sessions', [StudentController::class, 'storeStudySession']);
        Route::get('study-sessions/{id}', [StudentController::class, 'showStudySession']);
        Route::put('study-sessions/{id}', [StudentController::class, 'updateStudySession']);
        Route::delete('study-sessions/{id}', [StudentController::class, 'destroyStudySession']);
        Route::get('homework', [HomeworkController::class, 'myHomework']);
        Route::get('homework-submissions', [HomeworkSubmissionController::class, 'index']);
        Route::get('quizzes', [QuizController::class, 'availableForStudent']);
        Route::get('dashboard', [StudentController::class, 'dashboard']);
    });

    Route::prefix('exam-management')->group(function () {
        Route::get('quizzes/{quiz}/results', [QuizController::class, 'resultsWithRank']);
        Route::post('quizzes/{quiz}/participants', [QuizController::class, 'assignParticipants']);
        Route::post('quiz-sessions/auto-expire', [QuizSessionController::class, 'autoExpire']);
    });
});
