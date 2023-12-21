<?php

declare(strict_types=1);

use App\Http\Controllers\Event;
use Illuminate\Support\Facades\Route;

$route = function () {
    Route::controller(Event::class)->group(function () {
        Route::post('get_fnb_member_coupon', 'getFnbMemberCoupon');
        Route::post('get_subscription_member_coupon', 'getSubscriptionMemberCoupon');
        Route::post('get_subscription_member_coupon', 'getSubscriptionMemberCoupon');
        Route::post('issue_fnb_member_coupon', 'issueFnbMemberCoupon');
    });
};

Route::group(['prefix' => 'event', 'middleware' => 'auth:api'], $route);
Route::group(['prefix' => 'ext/event', 'middleware' => 'auth:sanctum'], $route);
