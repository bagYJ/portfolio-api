<?php
declare(strict_types=1);

use App\Http\Controllers\Subscription;
use Illuminate\Support\Facades\Route;

$route = function () {
    Route::controller(Subscription::class)->group(function () {
        Route::get('', 'list');
        Route::get('{no}', 'detail')->where('no', '[0-9]+');
        Route::post('payment', 'payment')->name('auth');
        Route::put('payment', 'paymentChange')->name('auth');
        Route::get('affiliate', 'affiliate');
        Route::post('affiliate', 'registCoupon')->name('auth');
        Route::post('admin-affiliate', 'registCouponAdmin')->name('admin-auth');
        Route::post('batch', 'batch')->name('admin-auth');

        Route::group(['prefix' => 'order'], function () {
            Route::get('brief', 'orderListBrief')->name('auth');
            Route::get('{no}', 'orderDetail')->where('no', '[0-9]+')->name('auth');
            Route::get('me', 'me')->name('auth');
            Route::get('refund', 'refund')->name('auth');
            Route::post('change', 'change')->name('auth');
        });
    });
};

Route::group(['prefix' => 'subscription', 'middleware' => 'auth:api'], $route);
Route::group(['prefix' => 'ext/subscription', 'middleware' => 'auth:sanctum'], $route);