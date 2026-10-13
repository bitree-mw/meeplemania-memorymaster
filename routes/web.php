<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Api\PlayController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\ArenaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RulesController;
use App\Http\Middleware\LogAdminActivity;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public pages
|--------------------------------------------------------------------------
*/
Route::get('/', HomeController::class)->name('home');
Route::get('/play/{game}/{mode}', ArenaController::class)->whereIn('mode', ['classic', 'timed', 'once'])->name('arena');
Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');
Route::get('/rules', RulesController::class)->name('rules');
Route::get('/players/{username}', [ProfileController::class, 'show'])->name('players.show');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:auth');
    Route::get('/admin/login', [AuthenticatedSessionController::class, 'createAdmin'])->name('admin.login');
    Route::post('/admin/login', [AuthenticatedSessionController::class, 'storeAdmin'])->middleware('throttle:auth');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:auth');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:auth')->name('password.email');
    Route::get('/reset-password/{username}/{token}', [PasswordResetController::class, 'reset'])->where('token', '[A-Za-z0-9]{64}')->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:auth')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'me'])->name('profile');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/district-request', [ProfileController::class, 'requestDistrictChange'])->middleware('throttle:6,1')->name('profile.district-request');
    Route::delete('/profile/district-request', [ProfileController::class, 'cancelDistrictChange'])->name('profile.district-request.cancel');
    Route::post('/district-notices/{districtRequest}/dismiss', [ProfileController::class, 'dismissDistrictNotice'])->name('district-notice.dismiss');
});

/*
|--------------------------------------------------------------------------
| Gameplay JSON API (session-authenticated, CSRF-protected)
|--------------------------------------------------------------------------
*/
Route::prefix('api/play')->group(function () {
    Route::post('/sessions', [PlayController::class, 'store'])->middleware('throttle:game-start');

    Route::middleware('throttle:game-round')->group(function () {
        Route::get('/sessions/{session}', [PlayController::class, 'show']);
        Route::post('/sessions/{session}/sync', [PlayController::class, 'sync']);
        Route::post('/sessions/{session}/finish', [PlayController::class, 'finish']);
    });
});

Route::prefix('api/presence')->group(function () {
    Route::post('/heartbeat', [PresenceController::class, 'heartbeat'])->middleware('throttle:presence');
    Route::get('/counts', [PresenceController::class, 'counts'])->middleware('throttle:presence');
});

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth', 'can:access-admin', LogAdminActivity::class])->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings/{scope}/{section}', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings/{scope}/{section}', [Admin\SettingsController::class, 'reset'])->name('settings.reset');

    Route::get('/players', [Admin\PlayerController::class, 'index'])->name('players');
    Route::get('/players/{player}', [Admin\PlayerController::class, 'show'])->name('players.show');
    Route::post('/players/{player}/disable', [Admin\PlayerController::class, 'disable'])->name('players.disable');
    Route::post('/players/{player}/enable', [Admin\PlayerController::class, 'enable'])->name('players.enable');
    Route::post('/players/{player}/password-reset', [Admin\PlayerController::class, 'sendPasswordReset'])->name('players.password-reset');
    Route::post('/players/{player}/promote', [Admin\AdminAccountController::class, 'promote'])->name('players.promote');
    Route::post('/players/{player}/revoke', [Admin\AdminAccountController::class, 'revoke'])->name('players.revoke');

    Route::get('/district-requests', [Admin\DistrictRequestController::class, 'index'])->name('district-requests');
    Route::post('/district-requests/{districtRequest}/approve', [Admin\DistrictRequestController::class, 'approve'])->name('district-requests.approve');
    Route::post('/district-requests/{districtRequest}/reject', [Admin\DistrictRequestController::class, 'reject'])->name('district-requests.reject');

    Route::get('/admins', [Admin\AdminAccountController::class, 'index'])->name('admins');
    Route::post('/admins', [Admin\AdminAccountController::class, 'store'])->name('admins.store');

    Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews');
    Route::post('/sessions/{session}/approve', [Admin\ReviewController::class, 'approve'])->name('sessions.approve');
    Route::post('/sessions/{session}/void', [Admin\ReviewController::class, 'void'])->name('sessions.void');

    Route::get('/challenges', [Admin\ChallengeController::class, 'index'])->name('challenges');
    Route::put('/challenges', [Admin\ChallengeController::class, 'upsert'])->name('challenges.upsert');

    Route::get('/seasons', [Admin\SeasonController::class, 'index'])->name('seasons');
    Route::post('/seasons', [Admin\SeasonController::class, 'store'])->name('seasons.store');
    Route::put('/seasons/{season}', [Admin\SeasonController::class, 'update'])->name('seasons.update');
    Route::delete('/seasons/{season}', [Admin\SeasonController::class, 'destroy'])->name('seasons.destroy');

    Route::get('/achievements', [Admin\AchievementController::class, 'index'])->name('achievements');
    Route::post('/achievements', [Admin\AchievementController::class, 'store'])->name('achievements.store');
    Route::put('/achievements/{achievement}', [Admin\AchievementController::class, 'update'])->name('achievements.update');

    Route::get('/leaderboards', Admin\LeaderboardReviewController::class)->name('leaderboards');
});
