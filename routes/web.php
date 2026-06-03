<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', 'dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::inertia('dashboard/import', 'import')->name('dashboard.import');
    Route::inertia('dashboard/utilities', 'utilities')->name('dashboard.utilities');
    
    // PPh 23 Tax Assistant API Routes
    Route::post('/api/test-connection', [App\Http\Controllers\OdooController::class, 'testConnection']);
    Route::post('/api/sync', [App\Http\Controllers\OdooController::class, 'sync']);
    Route::post('/api/upload-excel', [App\Http\Controllers\ExcelController::class, 'upload']);

    // Odoo secure configuration
    Route::get('/api/odoo-config', [App\Http\Controllers\OdooController::class, 'getConfig']);
    Route::post('/api/odoo-config', [App\Http\Controllers\OdooController::class, 'saveConfig']);

    // User management endpoints
    Route::get('/api/users', [App\Http\Controllers\UtilityController::class, 'getUsers']);
    Route::post('/api/users', [App\Http\Controllers\UtilityController::class, 'saveUser']);
    Route::delete('/api/users/{user}', [App\Http\Controllers\UtilityController::class, 'deleteUser']);

    // Department management endpoints
    Route::get('/api/departments', [App\Http\Controllers\UtilityController::class, 'getDepartments']);
    Route::post('/api/departments', [App\Http\Controllers\UtilityController::class, 'saveDepartment']);
    Route::delete('/api/departments/{department}', [App\Http\Controllers\UtilityController::class, 'deleteDepartment']);

    // System settings endpoints (Utilities page unlock protection)
    Route::post('/api/utilities/unlock', [App\Http\Controllers\UtilityController::class, 'unlockUtilities']);
    Route::get('/api/settings/unlock-password', [App\Http\Controllers\UtilityController::class, 'getUnlockPassword']);
    Route::post('/api/settings/unlock-password', [App\Http\Controllers\UtilityController::class, 'saveUnlockPassword']);

    // Dashboard data endpoints
    Route::get('/api/journal-entries', [App\Http\Controllers\OdooController::class, 'getEntries']);
    Route::delete('/api/journal-entries', [App\Http\Controllers\OdooController::class, 'clearEntries']);
    Route::post('/api/process', [App\Http\Controllers\ExcelController::class, 'process']);
    Route::get('/api/export', [App\Http\Controllers\ExcelController::class, 'export']);
});

require __DIR__.'/settings.php';
