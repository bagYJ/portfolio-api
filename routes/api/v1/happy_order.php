<?php

use App\Http\Controllers\HappyOrder;
use Illuminate\Support\Facades\Route;

$route = function () {
    Route::controller(HappyOrder::class)->group(function () {
        Route::post('arr_time', 'arrTime');
    });
};

Route::group(['prefix' => 'happy_order', 'middleware' => 'auth:api'], $route);
Route::group(['prefix' => 'ext/happy_order', 'middleware' => 'auth:sanctum'], $route);
