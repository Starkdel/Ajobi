<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\AuthController;

use Illuminate\Support\Facades\Artisan;

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

Route::get('/storage-link', function () {

    Artisan::call('storage:link');

    return 'Storage linked successfully';

});


Route::get('/test-mail', function () {
    Mail::raw('Hello from Gmail data SMTP!', function ($message) {
        $message->to('soyombotomiwa0502@gmail.com')
                ->subject('SMTP Test Email');
        
    });

    return "Email sent!";
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/verify-account/{token}', [AuthController::class, 'verifyAccount']);
Route::get('/test', function () {
    return view('test');
});

Route::get('/csrf-token', function (Request $request) {
    return response()->json([
        'csrf_token' => csrf_token(),
        'session_cookie' => $request->cookie('reportwriting_session'),
    ]);
});
