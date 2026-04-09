<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

// ===== PUBLIC ROUTES =====
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ===== PROTECTED ROUTES =====
Route::middleware('auth:api')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Profil
    Route::get('/profile',  [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);

    // Skill
    Route::get('/skills',                    [SkillController::class, 'index']);
    Route::get('/skills/user',               [SkillController::class, 'getUserSkills']);
    Route::post('/skills/user',              [SkillController::class, 'saveUserSkills']);
    Route::get('/skills/division/{id}',      [SkillController::class, 'byDivision']);

    // Divisi
    Route::get('/divisions', [DivisionController::class, 'index']);

    // Feedback - M1
    Route::post('/feedbacks',    [FeedbackController::class, 'store']);
    Route::get('/feedbacks/my',  [FeedbackController::class, 'myFeedbacks']);

    // Feedback - Admin
    Route::get('/admin/feedbacks',           [FeedbackController::class, 'adminIndex']);
    Route::post('/admin/feedbacks/{id}/approve', [FeedbackController::class, 'approve']);
    Route::post('/admin/feedbacks/{id}/reject',  [FeedbackController::class, 'reject']);
    
});