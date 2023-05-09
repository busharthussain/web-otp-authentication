<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::group(['middleware' => ['web']], function () {
    Route::controller(App\Http\Controllers\Auth\AuthOtpController::class)->group(function(){
        Route::post('otp', 'login')->name('otp');
        Route::post('/otp/generate', 'generate')->name('otp.generate');
        Route::get('/otp/verification/{user_id}', 'verification')->name('otp.verification');
        Route::post('otp/login', 'loginWithOtp')->name('otp.getlogin');
        Route::get('otp', 'login')->name('otp');
    });
});