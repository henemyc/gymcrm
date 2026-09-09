<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScraperController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('today')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/today', [DashboardController::class, 'today'])->name('today');
    Route::get('/today/data', [DashboardController::class, 'todayData'])->name('today.data');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports.index');
    Route::get('/reports/data', [DashboardController::class, 'reportData'])->name('reports.data');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::get('/leads', [DashboardController::class, 'leadsPage'])->name('leads.index');
    Route::get('/leads/live', [DashboardController::class, 'leadsJson'])->name('leads.live');
    Route::get('/leads/datatable', [DashboardController::class, 'leadsJson'])->name('leads.datatable');
    Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
    Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::get('/leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
    Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::post('/leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status');
    Route::post('/leads/{lead}/outcome', [LeadController::class, 'outcome'])->name('leads.outcome');
    Route::post('/leads/{lead}/notes', [LeadController::class, 'storeNote'])->name('leads.notes.store');
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');

    Route::post('/leads/bulk/assign', [LeadController::class, 'bulkAssign'])->name('leads.bulk.assign');
    Route::post('/leads/bulk/status', [LeadController::class, 'bulkStatus'])->name('leads.bulk.status');
    Route::post('/leads/bulk/delete', [LeadController::class, 'bulkDelete'])->name('leads.bulk.delete');
    Route::post('/leads/bulk/export', [ImportExportController::class, 'exportSelected'])->name('leads.bulk.export');
    Route::get('/export', [ImportExportController::class, 'export'])->name('leads.export');

    Route::middleware('role:admin')->group(function () {
        Route::get('/import', [ImportExportController::class, 'importForm'])->name('leads.import.form');
        Route::post('/import', [ImportExportController::class, 'import'])->name('leads.import');
        Route::get('/import/template', [ImportExportController::class, 'template'])->name('leads.import.template');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/datatable', [UserController::class, 'datatable'])->name('users.datatable');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

        Route::get('/scraper', [ScraperController::class, 'index'])->name('scraper.index');
        Route::get('/scraper/autocomplete', [ScraperController::class, 'autocomplete'])->name('scraper.autocomplete');
        Route::post('/scraper/search', [ScraperController::class, 'search'])->name('scraper.search');
        Route::post('/scraper/more', [ScraperController::class, 'more'])->name('scraper.more');
        Route::post('/scraper/enrich', [ScraperController::class, 'enrich'])->name('scraper.enrich');
        Route::post('/scraper/assign', [ScraperController::class, 'assign'])->name('scraper.assign');
        Route::get('/scraper/jobs', [ScraperController::class, 'jobs'])->name('scraper.jobs');

        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-maps', [SettingController::class, 'testMaps'])->name('settings.test-maps');

        Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
        Route::get('/activity/datatable', [ActivityController::class, 'datatable'])->name('activity.datatable');
    });
});
