<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AvatarController;

// Existing avatar routes.
Route::get('/avatar/{name}.webp', [AvatarController::class, 'generate'])->name('avatar.generate');
Route::get('/avatar/{name}',    [AvatarController::class, 'generate'])->name('avatar.generate');
Route::get('/avatar.webp',      [AvatarController::class, 'generate']);
Route::get('/avatar',           [AvatarController::class, 'generate']);

// New agent routes (name and optional emotion).
Route::get('/agent/{name}/{emotion}.webp', [AvatarController::class, 'generateAgent'])->name('agent.generate');
Route::get('/agent/{name}/{emotion}',      [AvatarController::class, 'generateAgent'])->name('agent.generate');
Route::get('/agent/{name}.webp',           [AvatarController::class, 'generateAgent'])->name('agent.generate');
Route::get('/agent/{name}',                [AvatarController::class, 'generateAgent'])->name('agent.generate');
Route::get('/agent.webp',                  [AvatarController::class, 'generateAgent']);
Route::get('/agent',                       [AvatarController::class, 'generateAgent']);
