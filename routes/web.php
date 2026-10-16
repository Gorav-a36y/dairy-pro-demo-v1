<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\BatchProductionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DailyRoundController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\WasteController;
use App\Http\Controllers\KhataController;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\MilkCollectionController;
use App\Http\Controllers\PaymentMethodController;
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
    Route::get('/sales-history/export-csv', [SaleController::class, 'exportCsv'])->name('sales.exportCsv');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');

    Route::resource('customers', CustomerController::class)->except(['show', 'destroy']);

    Route::get('/daily-round', [DailyRoundController::class, 'index'])->name('daily-round.index');
    Route::post('/daily-round', [DailyRoundController::class, 'store'])->name('daily-round.store');
    Route::get('/daily-round/history', [DailyRoundController::class, 'history'])->name('daily-round.history');

    // Procurement
    Route::get('/milk-collections', [MilkCollectionController::class, 'index'])->name('milk-collections.index');
    Route::post('/milk-collections', [MilkCollectionController::class, 'store'])->name('milk-collections.store');
    Route::get('/milk-collections/export-csv', [MilkCollectionController::class, 'exportCsv'])->name('milk-collections.exportCsv');
    Route::get('milk-collections/{milkCollection}/receipt', [MilkCollectionController::class, 'receipt'])
        ->name('milk-collections.receipt');

    // FIXED: destroy() replaced with toggleActive() — excluded, toggle route added below
    Route::resource('suppliers', SupplierController::class)->only(['index', 'create', 'store']);

    // Production — Batch Production now lives on the Products page itself
    // (see ProductController::index); only the actions its forms/links call
    // still need routes here.
    Route::get('/batches/export-csv', [BatchProductionController::class, 'exportCsv'])->name('batches.exportCsv');
    Route::post('/batches/{product}', [BatchProductionController::class, 'store'])->name('batches.store');

    // FIXED: destroy() replaced with toggleActive() — excluded, toggle route added below
    Route::resource('products', ProductController::class)->except(['show', 'destroy']);
    Route::patch('/products/{product}/toggle', [ProductController::class, 'toggleActive'])->name('products.toggle');

    // FIXED: added ['show', 'destroy'] — show() doesn't exist, destroy() replaced with toggleActive()
    Route::resource('raw-materials', RawMaterialController::class)->except(['show', 'destroy']);
    Route::patch('/raw-materials/{raw_material}/toggle', [RawMaterialController::class, 'toggleActive'])->name('raw-materials.toggle');

    // Waste Management — deducts stock from either Raw Materials or Products
    Route::get('/waste', [WasteController::class, 'index'])->name('waste.index');
    Route::post('/waste', [WasteController::class, 'store'])->name('waste.store');
    Route::get('/waste/export-csv', [WasteController::class, 'exportCsv'])->name('waste.exportCsv');

    // Accounts — Worker Khata (salary/advance/bonus ledger) and its
    // receipts have been removed entirely. Workers themselves are still
    // managed on the Workers page; Khata now only covers Customers/Suppliers.
    Route::get('/khata', [KhataController::class, 'index'])->name('khata.index');
    Route::get('/khata/export-csv', [KhataController::class, 'exportCsv'])->name('khata.exportCsv');
    Route::post('/khata/customer', [KhataController::class, 'storeCustomerTransaction'])->name('khata.customer.store');
    Route::post('/khata/supplier', [KhataController::class, 'storeSupplierTransaction'])->name('khata.supplier.store');

    // FIXED: method names now match KhataController (customerPaymentReceipt / supplierPaymentReceipt)
    Route::get('/khata/customer/receipt/{transaction}', [KhataController::class, 'customerPaymentReceipt'])
        ->name('khata.customer.receipt');
    Route::get('/khata/supplier/receipt/{transaction}', [KhataController::class, 'supplierPaymentReceipt'])
        ->name('khata.supplier.receipt');

    // FIXED: destroy() replaced with toggleActive() — excluded, toggle route added below
    Route::resource('workers', WorkerController::class)->except(['destroy']);
    Route::patch('/workers/{worker}/toggle', [WorkerController::class, 'toggleActive'])->name('workers.toggle');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    // Help page
    Route::get('/help', fn () => view('help.index'))->name('help.index');

    // Assistant + Settings
    Route::get('/ai-assistant', [AiAssistantController::class, 'index'])->name('ai.index');
    Route::post('/ai-assistant', [AiAssistantController::class, 'send'])->name('ai.send');
    Route::post('/ai-assistant/clear', [AiAssistantController::class, 'clear'])->name('ai.clear');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

    // Payment Methods (managed from Settings) — Khata is seeded as a system
    // method and PaymentMethodController blocks edits/deletes on it.
    Route::post('/settings/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::patch('/settings/payment-methods/{paymentMethod}/toggle', [PaymentMethodController::class, 'toggleActive'])->name('payment-methods.toggle');
});