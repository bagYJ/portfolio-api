<?php

use App\Http\Controllers\Push;
use Illuminate\Support\Facades\Route;

$route = function () {
    Route::controller(Push::class)->group(function () {
        Route::post('', 'send');
    });
};

Route::group(['prefix' => 'push', 'middleware' => 'auth:api'], $route);
Route::group(['prefix' => 'ext/push', 'middleware' => 'auth:sanctum'], $route);