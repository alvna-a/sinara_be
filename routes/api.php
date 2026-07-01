<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DataMahasiswaController;

// ===== PUBLIC =====
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// Company publik (untuk preview tanpa login)
Route::get('/companies',      [CompanyController::class, 'index']);
Route::get('/companies/{id}', [CompanyController::class, 'show']);
Route::get('/companies/{companyId}/divisions/{divisionId}', [DivisionController::class, 'show']);

// ===== PROTECTED =====
Route::middleware('auth:api')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Profil
    Route::get('/profile',  [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::put('/account', [ProfileController::class, 'updateAccount']);

    // Skill
    Route::get('/skills',               [SkillController::class, 'index']);
    Route::get('/skills/user',          [SkillController::class, 'getUserSkills']);
    Route::post('/skills/user',         [SkillController::class, 'saveUserSkills']);
    Route::get('/skills/division/{id}', [SkillController::class, 'byDivision']);

    // Divisi
    Route::get('/divisions', [DivisionController::class, 'index']);

    // Feedback — Mahasiswa Alumni (M1)
    Route::post('/feedbacks',   [FeedbackController::class, 'store']);
    Route::get('/feedbacks/my', [FeedbackController::class, 'myFeedbacks']);

    // Rekomendasi — Mahasiswa Calon (M2)
    Route::post('/recommendations',                     [RecommendationController::class, 'generate']);
    Route::get('/recommendations/sessions',             [RecommendationController::class, 'mySessions']);
    Route::get('/recommendations/sessions/{sessionKey}',[RecommendationController::class, 'showSession']);
    Route::get('/recommendations/{id}',                 [RecommendationController::class, 'show']);

    // ===== ADMIN =====
    Route::prefix('admin')->group(function () {
        // Feedback management
        Route::get('/feedbacks',                    [FeedbackController::class, 'adminIndex']);
        Route::post('/feedbacks/{id}/approve',      [FeedbackController::class, 'approve']);
        Route::post('/feedbacks/{id}/reject',       [FeedbackController::class, 'reject']);

        // Company management
        Route::get('/companies',        [CompanyController::class, 'adminIndex']);
        Route::put('/companies/{id}',   [CompanyController::class, 'update']);
        Route::delete('/companies/{id}',[CompanyController::class, 'destroy']);

        // Data Mahasiswa management
        Route::get('/data-mahasiswa',                   [DataMahasiswaController::class, 'index']);
        Route::get('/data-mahasiswa/{id}',               [DataMahasiswaController::class, 'show']);
        Route::post('/data-mahasiswa/{id}/switch-role',  [DataMahasiswaController::class, 'switchRole']);
        Route::post('/data-mahasiswa/{id}/reset-role',   [DataMahasiswaController::class, 'resetRoleOverride']);

        Route::get('/nlp/health',    [RecommendationController::class, 'health']);
        Route::post('/nlp/retrain',  [RecommendationController::class, 'triggerRetrain']);
    });
});