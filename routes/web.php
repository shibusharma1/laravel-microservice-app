<?php

use App\Http\Controllers\BusySyncController;
use App\Http\Controllers\Company\Admin\ERPNextOAuthController;
use App\Http\Controllers\Company\Admin\ZohoOAuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CustomerSyncController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('erpnext')->name('erpnext.')->group(function () {
    Route::get('/connect',    [ERPNextOAuthController::class, 'showConnect'])->name('connect');
    Route::get('/callback',   [ERPNextOAuthController::class, 'callback'])->name('callback');
    Route::post('/disconnect', [ERPNextOAuthController::class, 'disconnect'])->name('disconnect');
    Route::get('/ping',       [ERPNextOAuthController::class, 'ping'])->name('ping');
});


Route::prefix('erpnext')->name('erpnext.')->group(function () {
    Route::get('/connect',    [ERPNextOAuthController::class, 'showConnect'])->name('connect');
    Route::get('/callback',   [ERPNextOAuthController::class, 'callback'])->name('callback');
    Route::post('/disconnect', [ERPNextOAuthController::class, 'disconnect'])->name('disconnect');
    Route::get('/ping',       [ERPNextOAuthController::class, 'ping'])->name('ping');
});

Route::prefix('zoho')->name('zoho.')->group(function () {
    Route::get('/connect',    [ZohoOAuthController::class, 'redirect'])->name('connect');
    Route::get('/callback',   [ZohoOAuthController::class, 'callback'])->name('callback');
    Route::post('/disconnect', [ZohoOAuthController::class, 'disconnect'])->name('disconnect');
});

// Customer CRUD
Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
Route::get('/customer/create', [CustomerController::class, 'create'])->name('customers.create');
Route::post('/customer', [CustomerController::class, 'store'])->name('customers.store');
Route::get('/customer/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
Route::put('/customer/{customer}', [CustomerController::class, 'update'])->name('customers.update');
Route::delete('/customer/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

// Product CRUD
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/product/create', [ProductController::class, 'create'])->name('products.create');
Route::post('/product', [ProductController::class, 'store'])->name('products.store');
Route::get('/product/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
Route::put('/product/{product}', [ProductController::class, 'update'])->name('products.update');
Route::delete('/product/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

//Order CRUD
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/order/create', [OrderController::class, 'create'])->name('orders.create');
Route::post('/order', [OrderController::class, 'store'])->name('orders.store');
Route::get('/order/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
Route::put('/order/{order}', [OrderController::class, 'update'])->name('orders.update');
Route::delete('/order/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');

//Payment CRUD
Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
Route::get('/payment/create', [PaymentController::class, 'create'])->name('payments.create');
Route::post('/payment', [PaymentController::class, 'store'])->name('payments.store');
Route::get('/payment/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
Route::put('/payment/{payment}', [PaymentController::class, 'update'])->name('payments.update');
Route::delete('/payment/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');


//job url to sync the customer

Route::post('/sync/customers/start', [SyncController::class, 'syncCustomers']);

Route::post('/sync/products/start', [SyncController::class, 'syncProducts']);

Route::post('/sync/payments/start', [SyncController::class, 'syncPayments']);

Route::post('/sync/sales-orders/start', [SyncController::class, 'syncSalesOrders']);

Route::get('/busy/sync/parties', [BusySyncController::class, 'parties']);
Route::get('/busy/sync/products', [BusySyncController::class, 'products']);
Route::get('/busy/sync/units', [BusySyncController::class, 'units']);
Route::get('/busy/sync/taxes', [BusySyncController::class, 'taxes']);
Route::get('/busy/sync/item-categories',[BusySyncController::class, 'itemCategories']);