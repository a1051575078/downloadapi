<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppleController;
use App\Http\Controllers\BaiduController;
use App\Http\Controllers\ComputerController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\AdminController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('/aa',[AppleController::class,'index']);
Route::get('/cc',[BaiduController::class,'index']);
Route::get('/test',[IndexController::class,'test']);
//chatgpt包https://github.com/openai-php/client
//key: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
Route::get('/spiders',[AdminController::class,'spiders']);
//以下是后台路由
Route::post('/login',[AdminController::class,'login']);
Route::group(['middleware'=>['auth:api']],function(){

    Route::post('/userinfo',[AdminController::class,'userinfo']);
    Route::post('/logout',[AdminController::class,'logout']);
});



//以下是前台路由
Route::get('/home',[IndexController::class,'home']);
Route::get('/popular',[IndexController::class,'popular']);
Route::get('/apps',[IndexController::class,'apps']);
Route::get('/configs',[IndexController::class,'configs']);
Route::get('/navigations',[IndexController::class,'navigations']);
Route::get('/abouts',[IndexController::class,'abouts']);
Route::get('/map',[IndexController::class,'map']);
Route::get('/info',[IndexController::class,'info']);
Route::get('/new',[IndexController::class,'new']);
Route::get('/news',[IndexController::class,'news']);
Route::get('/list',[IndexController::class,'list']);
Route::get('{id}.png',[IndexController::class,'png'])->where('id','[0-9]+');
Route::get('/ios/{id}.ipa',[IndexController::class,'download'])->where('id','[0-9]+');
Route::get('/android/{id}.apk',[IndexController::class,'download'])->where('id','[0-9]+');
Route::get('/computer/{id}.exe',[IndexController::class,'download'])->where('id','[0-9]+');
Route::get('/harmonyos/{id}.hap',[IndexController::class,'download'])->where('id','[0-9]+');




Route::post('/search',[IndexController::class,'search']);
Route::post('/goodornot',[IndexController::class,'goodornot']);
