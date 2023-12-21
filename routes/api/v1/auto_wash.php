<?php

declare(strict_types=1);

use App\Http\Controllers\AutoWash;
use Illuminate\Support\Facades\Route;

$route = function () {
    Route::controller(AutoWash::class)->group(function () {
        Route::get('info/{noShop}', 'info');
        Route::post('intro', 'intro')->name('auth');
        Route::post('payment', 'payment');
        Route::post('order_complete', 'orderComplete')->name('auth');
    });
};

Route::group(['prefix' => 'auto_wash', 'middleware' => 'auth:api'], $route);
Route::group(['prefix' => 'ext/auto_wash', 'middleware' => 'auth:sanctum'], $route);
