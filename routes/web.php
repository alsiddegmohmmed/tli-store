<?php

use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/pos')->name('home');

Route::middleware('auth')->group(function (): void {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/products', [PosController::class, 'lookup'])->name('pos.products');
    Route::post('/pos/quote', [PosController::class, 'quote'])->name('pos.quote');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->middleware('can:manage-products')->name('products.store');
});
