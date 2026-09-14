<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookFileController;
use App\Http\Controllers\ReadingProgressController;
use App\Http\Controllers\Api\AuthController;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        Route::get('/books', [BookController::class, 'index']);
        Route::get('/books/genres', [BookController::class, 'genres']);
        Route::get('/books/stats', [BookController::class, 'stats']);
        Route::get('/books/continue', [BookController::class, 'continueReading']);
        Route::get('/books/search', [BookController::class, 'search']);
        Route::get('/books/{id}', [BookController::class, 'show']);
        Route::get('/books/{id}/file', [BookFileController::class, 'show']);
        Route::get('/books/{id}/progress', [ReadingProgressController::class, 'show']);
        Route::put('/books/{id}/progress', [ReadingProgressController::class, 'update'])->middleware('throttle:60,1');
        Route::post('/books', [BookController::class, 'store']);
        Route::post('/books/upload', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'delete']);
    });

    Route::get('/books/fetch-cover', [BookController::class, 'fetchCover']);
});
