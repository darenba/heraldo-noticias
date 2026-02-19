<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EditionController;
use App\Http\Controllers\Admin\JobStatusController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Public\ArticleController;
use Illuminate\Support\Facades\Route;

// ── Public portal (no auth required) ──────────────────────────────────────
Route::get('/', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{article}', [ArticleController::class, 'show'])->name('articles.show');
Route::get('/tags/{tag}', [ArticleController::class, 'byTag'])->name('articles.by-tag');

// ── Admin auth routes ──────────────────────────────────────────────────────
Route::get('/admin/login', [AdminLoginController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'login'])->middleware('throttle:5,1');
Route::post('/admin/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');

// ── Admin panel (protected) ────────────────────────────────────────────────
Route::prefix('admin')
    ->middleware(['admin'])
    ->name('admin.')
    ->group(function () {
        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Editions resource (no edit/update — editions are immutable once created)
        Route::get('editions', [EditionController::class, 'index'])->name('editions.index');
        Route::get('editions/create', [EditionController::class, 'create'])->name('editions.create');
        Route::post('editions', [EditionController::class, 'store'])->name('editions.store');
        Route::get('editions/{edition}', [EditionController::class, 'show'])->name('editions.show');
        Route::delete('editions/{edition}', [EditionController::class, 'destroy'])->name('editions.destroy');

        // Job status (JSON endpoint for Alpine.js polling)
        Route::get('editions/{edition}/status', [JobStatusController::class, 'index'])->name('editions.status');
    });
