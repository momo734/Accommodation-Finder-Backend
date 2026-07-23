<?php

use App\Http\Controllers\Admin\AccommodationController as AdminAccommodationController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OwnerController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Community\AccommodationController as CommunityAccommodationController;
use App\Http\Controllers\Community\AuthController as CommunityAuthController;
use App\Http\Controllers\Community\FavouriteController;
use App\Http\Controllers\Community\ReviewController as CommunityReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Community / Public API
|--------------------------------------------------------------------------
*/
Route::post('/register', [CommunityAuthController::class, 'register']);
Route::post('/login', [CommunityAuthController::class, 'login']);

Route::get('/accommodations', [CommunityAccommodationController::class, 'index']);
Route::get('/accommodations/{accommodation}', [CommunityAccommodationController::class, 'show']);
Route::get('/accommodations/{accommodation}/related', [CommunityAccommodationController::class, 'related']);
Route::get('/accommodations/{accommodation}/reviews', [CommunityReviewController::class, 'index']);

Route::middleware(['auth:sanctum', 'community'])->group(function () {
    Route::post('/logout', [CommunityAuthController::class, 'logout']);
    Route::get('/profile', [CommunityAuthController::class, 'profile']);
    Route::put('/profile', [CommunityAuthController::class, 'updateProfile']);
    Route::put('/change-password', [CommunityAuthController::class, 'changePassword']);

    Route::get('/favourites', [FavouriteController::class, 'index']);
    Route::get('/favourites/ids', [FavouriteController::class, 'ids']);
    Route::post('/favourites/{accommodation}', [FavouriteController::class, 'store']);
    Route::delete('/favourites/{accommodation}', [FavouriteController::class, 'destroy']);

    Route::post('/accommodations/{accommodation}/reviews', [CommunityReviewController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Admin API
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/profile', [AdminAuthController::class, 'profile']);
        Route::put('/profile', [AdminAuthController::class, 'updateProfile']);
        Route::put('/change-password', [AdminAuthController::class, 'changePassword']);

        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::apiResource('accommodations', AdminAccommodationController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('/accommodations/{accommodation}/approve', [AdminAccommodationController::class, 'approve']);
        Route::post('/accommodations/{accommodation}/reject', [AdminAccommodationController::class, 'reject']);

        Route::apiResource('users', UserController::class)
            ->only(['index', 'show', 'update', 'destroy']);
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend']);
        Route::post('/users/{user}/activate', [UserController::class, 'activate']);

        Route::apiResource('owners', OwnerController::class)
            ->only(['index', 'show', 'destroy'])
            ->parameters(['owners' => 'owner']);
        Route::post('/owners/{owner}/suspend', [OwnerController::class, 'suspend']);
        Route::post('/owners/{owner}/activate', [OwnerController::class, 'activate']);

        Route::apiResource('reviews', AdminReviewController::class)
            ->only(['index', 'destroy']);
        Route::post('/reviews/{review}/hide', [AdminReviewController::class, 'hide']);

        Route::apiResource('reports', ReportController::class)
            ->only(['index', 'show', 'destroy']);
        Route::post('/reports/{report}/resolve', [ReportController::class, 'resolve']);
        Route::post('/reports/{report}/remove-listing', [ReportController::class, 'removeListing']);
    });
});
