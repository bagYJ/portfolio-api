<?php

use App\Http\Controllers\Event;
use App\Http\Controllers\Terms;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/event/fnb_event/{no_event}',[Event::class,'fnbEvent']);

// 약관
Route::get('/terms/biz_kinds',[Terms::class,'showBizKinds']);
Route::get('/terms/biz_kinds/{bizKind}/shops',[Terms::class,'showShops']);
Route::get('/terms/{termsCategory}',[Terms::class,'show']);
