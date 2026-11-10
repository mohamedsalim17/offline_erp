<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PosController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\AdvancedReportsController;
use App\Http\Controllers\FinancialReportsController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StocktakeController;
use App\Http\Controllers\TreasuryController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/lang/{lang}', [App\Http\Controllers\LanguageController::class, 'switch'])
    ->whereIn('lang', ['ar', 'en'])
    ->name('lang.switch');

Route::get('/company/logo', [SystemSettingsController::class, 'logo'])->name('company.logo');

Route::middleware(['auth', 'can:system-settings.update'])
    ->prefix('admin/system-settings')
    ->name('system-settings.')
    ->group(function () {
        Route::put('/', [SystemSettingsController::class, 'update'])->name('update');
    });

Route::middleware(['auth', 'can:backups.create'])->group(function () {
    Route::post('/admin/system-settings/backups', [SystemSettingsController::class, 'createBackup'])
        ->name('system-settings.backups.create');
});

Route::middleware(['auth', 'can:backups.upload'])->group(function () {
    Route::post('/admin/system-settings/backups/upload', [SystemSettingsController::class, 'uploadBackup'])
        ->name('system-settings.backups.upload');
});

Route::middleware(['auth', 'can:backups.view'])->group(function () {
    Route::get('/admin/system-settings/backups/{backup}/download', [SystemSettingsController::class, 'downloadBackup'])
        ->name('system-settings.backups.download');
});

Route::middleware(['auth', 'can:backups.restore'])->group(function () {
    Route::post('/admin/system-settings/backups/{backup}/restore', [SystemSettingsController::class, 'restoreBackup'])
        ->name('system-settings.backups.restore');
});

Route::middleware(['auth', 'can:backups.delete'])->group(function () {
    Route::delete('/admin/system-settings/backups/{backup}', [SystemSettingsController::class, 'deleteBackup'])
        ->name('system-settings.backups.delete');
});

Route::middleware(['auth', 'can:reports.export'])
    ->prefix('/admin/reports')
    ->name('reports.')
    ->group(function () {
        Route::get('/excel', [AdvancedReportsController::class, 'excel'])->name('excel');
        Route::get('/pdf', [AdvancedReportsController::class, 'pdf'])->name('pdf');
    });

Route::middleware(['auth', 'can:treasuries.view'])
    ->prefix('/admin/treasuries')
    ->name('treasuries.')
    ->group(function () {
        Route::get('/', [TreasuryController::class, 'index'])->name('index');
        Route::post('/', [TreasuryController::class, 'store'])->middleware('can:treasuries.manage')->name('store');
        Route::post('/transactions', [TreasuryController::class, 'record'])->middleware('can:treasuries.manage')->name('transactions.store');
        Route::post('/transfer', [TreasuryController::class, 'transfer'])->middleware('can:treasuries.manage')->name('transfer');
        Route::post('/{treasury}/toggle', [TreasuryController::class, 'toggle'])->middleware('can:treasuries.manage')->name('toggle');
        Route::post('/{treasury}/default', [TreasuryController::class, 'setDefault'])->middleware('can:treasuries.manage')->name('default');
    });

Route::middleware(['auth', 'can:stock-movements.view'])
    ->prefix('/admin/stock-movements')
    ->name('stock-movements.')
    ->group(function () {
        Route::get('/', [StockMovementController::class, 'index'])->name('index');
        Route::post('/', [StockMovementController::class, 'store'])->middleware('can:stock-movements.create')->name('store');
        Route::post('/{movement}/approve', [StockMovementController::class, 'approve'])->middleware('can:stock-movements.approve')->name('approve');
        Route::post('/{movement}/reject', [StockMovementController::class, 'reject'])->middleware('can:stock-movements.approve')->name('reject');
    });

Route::middleware(['auth', 'can:stocktakes.view'])
    ->prefix('/admin/stocktakes')
    ->name('stocktakes.')
    ->group(function () {
        Route::get('/', [StocktakeController::class, 'index'])->name('index');
        Route::post('/', [StocktakeController::class, 'store'])->middleware('can:stocktakes.create')->name('store');
        Route::get('/{stocktake}', [StocktakeController::class, 'edit'])->name('edit');
        Route::post('/{stocktake}/counts', [StocktakeController::class, 'saveCounts'])->middleware('can:stocktakes.create')->name('counts');
        Route::post('/{stocktake}/approve', [StocktakeController::class, 'approve'])->middleware('can:stocktakes.approve')->name('approve');
    });

Route::get('/admin/financial-reports', FinancialReportsController::class)
    ->middleware(['auth', 'can:finance.view'])
    ->name('finance.reports');

Route::middleware(['auth', 'can:sales.view'])->prefix('pos')->name('pos.')->group(function () {
    Route::get('/', [PosController::class, 'index'])->name('index');
    Route::get('/settings', [PosController::class, 'editSettings'])
        ->middleware('can:sales.settings')
        ->name('settings');
    Route::put('/settings', [PosController::class, 'updateSettings'])
        ->middleware('can:sales.settings')
        ->name('settings.update');
    Route::get('/products', [PosController::class, 'products'])->name('products');
    Route::get('/sales', [PosController::class, 'sales'])->name('sales');
    Route::get('/sales/{sale}/receipt', [PosController::class, 'receipt'])->name('receipt');
    Route::get('/sales/{sale}/warehouse-invoice', [PosController::class, 'warehouseInvoice'])->name('warehouse-invoice');
    Route::post('/checkout', [PosController::class, 'checkout'])
        ->middleware('can:sales.create')
        ->name('checkout');
});
