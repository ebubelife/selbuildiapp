<?php

use App\Http\Controllers\DeployController;
use Illuminate\Support\Facades\Route;

Route::post('deploy-hook', [DeployController::class, 'run'])->name('deploy-hook');

Route::view('/', 'welcome')->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
