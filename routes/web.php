<?php

use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/pos')->name('home');

Route::middleware('auth')->group(function (): void {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/products', [PosController::class, 'lookup'])->name('pos.products');
    Route::post('/pos/quote', [PosController::class, 'quote'])->name('pos.quote');
    Route::post('/pos/payment-preview', [PosController::class, 'paymentPreview'])->name('pos.paymentPreview');
    Route::post('/pos/sales', [SaleController::class, 'store'])->name('pos.sales');
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('/stock', [StockController::class, 'index'])->middleware('can:manage-products')->name('stock.index');
    Route::post('/stock/openings', [StockController::class, 'store'])->middleware('can:manage-products')->name('stock.openings');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->middleware('can:manage-products')->name('products.store');
});
