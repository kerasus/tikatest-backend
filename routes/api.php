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
    Route::apiResource('quizzes', QuizController::class);
    Route::apiResource('quiz-assignments', QuizClassAssignmentController::class);
    Route::apiResource('quiz-attempts', QuizAttemptController::class);
    Route::apiResource('disciplinary-cases', DisciplinaryCaseController::class);
    Route::apiResource('disciplinary-records', DisciplinaryRecordController::class);
    Route::apiResource('homework', HomeworkController::class);
    Route::apiResource('homework-submissions', HomeworkSubmissionController::class);
    Route::apiResource('messages', MessageController::class);
    Route::apiResource('pre-registrations', PreRegistrationController::class)->only(['index', 'store']);
    Route::apiResource('student-class-registrations', StudentClassRegistrationController::class)->except(['update']);
});
