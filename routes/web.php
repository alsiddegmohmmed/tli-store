<?php

use App\Http\Controllers\CashierShiftController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseBillController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReceiptController;
use App\Http\Controllers\ShiftCashTransferController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/pos')->name('home');

Route::middleware('auth')->group(function (): void {
    Route::middleware('can:manage-purchases')->group(function (): void {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::get('/suppliers/{supplier}/purchase-bill', [PurchaseBillController::class, 'create'])->name('purchases.create');
        Route::get('/purchases', [PurchaseBillController::class, 'index'])->name('purchases.index');
        Route::post('/purchases', [PurchaseBillController::class, 'store'])->name('purchases.store');
        Route::get('/purchases/{bill}', [PurchaseBillController::class, 'show'])->name('purchases.show');
    });
    Route::get('/shifts', [CashierShiftController::class, 'index'])->name('shifts.index');
    Route::post('/shifts', [CashierShiftController::class, 'store'])->name('shifts.store');
    Route::get('/shifts/{shift}', [CashierShiftController::class, 'show'])->name('shifts.show');
    Route::post('/shifts/{shift}/close', [CashierShiftController::class, 'close'])->name('shifts.close');
    Route::post('/shifts/{shift}/cash-transfers', [ShiftCashTransferController::class, 'store'])->name('shifts.cash-transfers.store');
    Route::get('/shifts/{shift}/cash-transfers/{transfer}', [ShiftCashTransferController::class, 'show'])->name('shifts.cash-transfers.show');
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/products', [PosController::class, 'lookup'])->name('pos.products');
    Route::post('/pos/quote', [PosController::class, 'quote'])->name('pos.quote');
    Route::post('/pos/payment-preview', [PosController::class, 'paymentPreview'])->name('pos.paymentPreview');
    Route::post('/pos/sales', [SaleController::class, 'store'])->name('pos.sales');
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('/sales/{sale}/receipt', [SaleReceiptController::class, 'show'])->name('sales.receipt');
    Route::get('/company', [CompanyProfileController::class, 'edit'])->middleware('can:manage-company')->name('company.edit');
    Route::put('/company', [CompanyProfileController::class, 'update'])->middleware('can:manage-company')->name('company.update');
    Route::get('/stock', [StockController::class, 'index'])->middleware('can:manage-products')->name('stock.index');
    Route::post('/stock/openings', [StockController::class, 'store'])->middleware('can:manage-products')->name('stock.openings');
    Route::middleware('can:manage-products')->group(function (): void {
        Route::get('/stock/counts', [StockCountController::class, 'index'])->name('stock.counts.index');
        Route::post('/stock/counts', [StockCountController::class, 'store'])->name('stock.counts.store');
        Route::get('/stock/counts/{count}', [StockCountController::class, 'show'])->name('stock.counts.show');
    });
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->middleware('can:manage-products')->name('products.store');
});
