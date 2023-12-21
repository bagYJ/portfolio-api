<?php

declare(strict_types=1);

use App\Http\Controllers\Wash;
use Illuminate\Support\Facades\Route;

$route = function () {
    Route::controller(Wash::class)->group(function () {
        Route::get('products', 'products');
        Route::get('price/{noShop}/{noProduct}', 'prices');
        Route::post('intro', 'intro')->name('auth');
        Route::post('order_complete', 'orderComplete')->name('auth');
    });
};

Route::group(['prefix' => 'wash', 'middleware' => 'auth:api'], $route);
Route::group(['prefix' => 'ext/wash', 'middleware' => 'auth:sanctum'], $route);
