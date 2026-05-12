<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;



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
Route::get('/userdata', function () {

    $users = DB::table('users')->get();

    return response()->json([
        'status' => 'success',
        'data' => $users
    ]);

});
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/auth/login', [AuthController::class, 'login'] )->name('login');


Route::post('/auth/register', [AuthController::class, 'register'] )->name('register');
Route::post('/onboarding/step1', [AuthController::class, 'onboardingstep1'] )->name('onboardingstep1');
Route::post('/onboarding/step2', [AuthController::class, 'onboardingstep2'] )->name('onboardingstep2');
Route::post('/onboarding/step3', [AuthController::class, 'onboardingstep3'] )->name('onboardingstep3');
Route::post('/onboarding/step4', [AuthController::class, 'onboardingstep4'] )->name('onboardingstep4');


Route::get('/auth/google/redirect', [AuthController::class, 'redirect'])->name('auth.google.redirect');

// Route to handle the callback from Google
Route::get('/auth/google/callback', [AuthController::class, 'callback'])->name('auth.google.callback');
