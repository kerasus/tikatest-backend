<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\TagController;
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
    Route::apiResource('users', UserController::class);

    Route::apiResource('tags', TagController::class);

    Route::post('places/import', [PlaceController::class, 'import']);
    Route::post('places/{place}/tags/sync', [PlaceController::class, 'syncTags']);
    Route::post('places/{place}/tags/attach', [PlaceController::class, 'attachTags']);
    Route::post('places/{place}/tags/detach', [PlaceController::class, 'detachTags']);
    Route::apiResource('places', PlaceController::class);
});
