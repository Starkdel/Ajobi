<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Ajoscorecontroller;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MarketPlaceController;
use App\Http\Controllers\EscrowController;

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
Route::post('/onboarding/step5', [Ajoscorecontroller::class, 'calculateAjoScore'] )->name('calculateAjoScore');
Route::get('/onboarding/progress/{email}', [AuthController::class, 'onboardingcheck'] )->name('onboardingcheck');

Route::get('/score/{userId}', [Ajoscorecontroller::class, 'getAjoreScore'] )->name('getAjoreScore');
Route::get('/score/{userId}/events', [Ajoscorecontroller::class, 'ajoevents'] )->name('ajoevents');
Route::get('/score/{userId}/history', [Ajoscorecontroller::class, 'Ajohistory'] )->name('Ajohistory');

//group
Route::post('/groups', [GroupController::class, 'creategroup'] )->name('creategroup');
Route::get('/groups/browse', [GroupController::class, 'browsegroup'] )->name('browsegroup');
Route::get('/mygroups/{userId}', [GroupController::class, 'myGroups'] )->name('myGroups');
Route::get('/groups/{groupId}', [GroupController::class, 'groupDetail'] )->name('groupDetail');
Route::post('/groups/{groupId}/join', [GroupController::class, 'joinGroup'] )->name('joinGroup');
Route::post('/groups/match', [GroupController::class, 'autoMatchGroup'] )->name('autoMatchGroup');

// bank statement
Route::post('/bank-statement/upload', [BankStatementController::class, 'upload']);
Route::get('/bank-statement/status',  [BankStatementController::class, 'status']);

//marketplace
Route::post('/listings', [MarketPlaceController::class, 'createListing'] )->name('createListing');
Route::get('/listings/browse', [MarketPlaceController::class, 'browseListings'] )->name('browseListings');
Route::get('/listings/{listingId}', [MarketPlaceController::class, 'getListingDetail'] )->name('getListingDetail');




//escrow
Route::post('/escrow', [EscrowController::class, 'createEscrow'] )->name('createEscrow');
Route::get('/escrow/user/{userId}', [EscrowController::class, 'getMyEscrows'] )->name('getMyEscrows');
Route::get('/escrow/{escrowId}', [EscrowController::class, 'getEscrowDetail'] )->name('getEscrowDetail');
Route::post('/escrow/{escrowId}/confirm', [EscrowController::class, 'confirmEscrow'] )->name('confirmEscrow');
Route::post('/escrow/{escrowId}/dispute', [EscrowController::class, 'raiseDispute'] )->name('raiseDispute');

//squad api

// Webhook — no auth middleware, Squad sends this
Route::post('/webhooks/squad', [WebhookController::class, 'handle']);

Route::get('/auth/google/redirect', [AuthController::class, 'redirect'])->name('auth.google.redirect');

// Route to handle the callback from Google
Route::get('/auth/google/callback', [AuthController::class, 'callback'])->name('auth.google.callback');
