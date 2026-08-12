<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\BatchProductionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\KhataController;
use App\Http\Controllers\MilkCollectionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Sales — POS + separate history
    Route::get('/sales', [SaleController::class, 'pos'])->name('sales.pos');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/sales-history', [SaleController::class, 'history'])->name('sales.history');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');

    Route::resource('customers', CustomerController::class)->except(['show']);

    // Procurement
    Route::get('/milk-collections', [MilkCollectionController::class, 'index'])->name('milk-collections.index');
    Route::post('/milk-collections', [MilkCollectionController::class, 'store'])->name('milk-collections.store');
    Route::delete('/milk-collections/{milkCollection}', [MilkCollectionController::class, 'destroy'])->name('milk-collections.destroy');

    Route::resource('suppliers', SupplierController::class)->except(['show']);

    // Production
    Route::get('/batches', [BatchProductionController::class, 'index'])->name('batches.index');
    Route::post('/batches/{product}', [BatchProductionController::class, 'store'])->name('batches.store');

    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('ingredients', IngredientController::class)->except(['show']);

    // Accounts
    Route::get('/khata', [KhataController::class, 'index'])->name('khata.index');
    Route::post('/khata/customer', [KhataController::class, 'storeCustomerTransaction'])->name('khata.customer.store');
    Route::post('/khata/supplier', [KhataController::class, 'storeSupplierTransaction'])->name('khata.supplier.store');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    // Assistant + Settings
    Route::get('/ai-assistant', [AiAssistantController::class, 'index'])->name('ai.index');
    Route::post('/ai-assistant', [AiAssistantController::class, 'send'])->name('ai.send');
    Route::post('/ai-assistant/clear', [AiAssistantController::class, 'clear'])->name('ai.clear');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
});
