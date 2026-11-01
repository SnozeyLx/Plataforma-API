<?php

use App\Enums\UserRole;
use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\BetController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ForgoutPasswordController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameRollbackController;
use App\Http\Controllers\PointsController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\RoomsController;
use App\Http\Controllers\RouletteController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VipController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\ApiAuth;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Webhooks
Route::post('/webhook/openpix', [WebhookController::class, 'openpix']);

Route::post('/webhook/novus', [WebhookController::class, 'PixIn']);
Route::post('/webhook/novus/pixout', [WebhookController::class, 'PixOut']);


Route::middleware(ApiAuth::class)->group(function () {
    Route::get('/refresh', [AuthController::class, 'refresh']);

});

// Ranking routes
Route::get('/ranking/daily', [RankingController::class, 'daily']);
Route::get('/ranking/weekly', [RankingController::class, 'weekly']);
Route::get('/ranking/monthly', [RankingController::class, 'monthly']);


// Users routes
Route::prefix("users")->middleware([ApiAuth::class . ":" . UserRole::Admin->value])->group(function () {
    Route::put('/{user}', [UserController::class, 'update'])->middleware(ApiAuth::class);
});

Route::prefix("users")->middleware([ApiAuth::class])->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile/update', [UserController::class, 'profile_update']);
    Route::put('/password/update', [UserController::class, 'password_update']);
    // Route::post('/profile/image', [UserController::class, 'image_update']);
    Route::get('/rooms', [UserController::class, 'rooms']);
    Route::get('/bets', [UserController::class, 'bets']);
    Route::get('/games', [UserController::class, 'gameHistory']);
});


// Cards routes
Route::prefix("cards")->middleware([ApiAuth::class])->group(function () {
    Route::get('/', [CardController::class, 'index']);
});

Route::prefix("rooms")->middleware([ApiAuth::class])->group(function () {
    Route::get('/', [RoomsController::class, 'index']);
    Route::get('/{room}', [RoomsController::class, 'show']);
    Route::post('/', [RoomsController::class, 'store'])->middleware([ApiAuth::class . ":" . UserRole::Dealer->value]);
    Route::get('/{room}/games', [RoomsController::class, 'games']);
    Route::get('/{room}/finish', [RoomsController::class, 'finish']);
});


// Game routes
Route::prefix("games")->middleware([ApiAuth::class . ":" . UserRole::Dealer->value])->group(function () {
    Route::get('/', [GameController::class, 'index'])->middleware();
    Route::post('/', [GameController::class, 'start'])->middleware();
    Route::get('/{game}', [GameController::class, 'show']);
    Route::put('/{game}/set-cards', [GameController::class, 'setCards']);
    Route::post('/{game}/finish', [GameController::class, 'finish']);
});


// Bet Routes
Route::prefix("bets")->middleware([ApiAuth::class])->group(function () {
    Route::post('/', [BetController::class, 'store']);
    Route::get('/list/{room}', [BetController::class, 'listBets']);
});


// Settings Routes
Route::prefix("settings")->middleware([ApiAuth::class . ":" . UserRole::Admin->value])->group(function () {
    Route::get('/', [SettingsController::class, 'index']);
    Route::post('/', [SettingsController::class, 'store']);
});

// Balance routes
Route::prefix("balance")->middleware([ApiAuth::class])->group(function () {
    Route::post('/deposit', [BalanceController::class, 'deposit']);
    Route::post('/withdraw', [BalanceController::class, 'withdraw']);
    Route::get('/statements', [BalanceController::class, 'statements']);
    Route::get('/statements/{id}', [BalanceController::class, 'statement']);
});

// Roulette routes
Route::prefix("roulettes")->middleware([ApiAuth::class])->group(function () {
    Route::get('/', [RouletteController::class, 'index']);
    Route::post('/store', [RouletteController::class, 'store']);
    Route::get('/spinconfig', [RouletteController::class, 'spinConfig']);
});

// Game Rollback
Route::prefix("rollback")->middleware([ApiAuth::class . ":" . UserRole::Dealer->value])->group(function () {
    Route::post('/{game}', [GameRollbackController::class, 'rollback']);
});


// Game Reset
Route::prefix("reset")->group(function () {
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send']);
    Route::post('/reset-password', [ResetPasswordController::class, 'reset']);
});


// Vip Routes
Route::prefix("vip")->middleware([ApiAuth::class])->group(function () {
    Route::get('/', [VipController::class, 'vip']);
});

// Consult Points
Route::prefix("points")->middleware([ApiAuth::class])->group(function () {
    Route::get('/', [PointsController::class, 'points']);
});


// Consult Suport
Route::prefix("support")->middleware([ApiAuth::class])->group(function () {
    Route::post('/email', [SupportController::class, 'send']);
});
