<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Ajoscorecontroller;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MarketPlaceController;
use App\Http\Controllers\EscrowController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
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




Route::post('/webhook', function (Request $request) {

    // 1. RAW BODY
    $payload = $request->getContent();

    // 2. SIGNATURE
    $signature = $request->header('x-squad-encrypted-body');

    // 3. VALIDATE SIGNATURE
    $computedSignature = strtoupper(
        hash_hmac('sha512', $payload, env('SQUAD_SECRET_KEY'))
    );

    if (!$signature || $signature !== $computedSignature) {

        Storage::append(
            'webhooks/invalid_signature.log',
            now() . ' | Invalid Signature | ' . $payload
        );

        return response()->json([
            'status' => 'error',
            'message' => 'Invalid signature'
        ], 401);
    }

    // 4. DECODE DATA
    $data = json_decode($payload, true);

    $event = $data['Event'] ?? null;
    $transactionRef = $data['TransactionRef'] ?? time();

    // 5. HANDLE EVENTS SEPARATELY

    /*
    |--------------------------------------------------------------------------
    | mandates.ready
    |--------------------------------------------------------------------------
    */

    if ($event === 'mandates.ready') {

        Storage::put(
            'mandates/ready/' . $transactionRef . '.json',
            json_encode($data, JSON_PRETTY_PRINT)
        );

        Storage::append(
            'logs/mandates_ready.log',
            now() . ' | Mandate Ready | ' . $transactionRef
        );

        // OPTIONAL:
        // update database
        // send notification
        // enable debit button
    }

    /*
    |--------------------------------------------------------------------------
    | mandates.approved
    |--------------------------------------------------------------------------
    */

    elseif ($event === 'mandates.approved') {

        Storage::put(
            'mandates/approved/' . $transactionRef . '.json',
            json_encode($data, JSON_PRETTY_PRINT)
        );

        Storage::append(
            'logs/mandates_approved.log',
            now() . ' | Mandate Approved | ' . $transactionRef
        );

        // OPTIONAL:
        // mark user as approved
        // activate subscription
        // start auto debit
    }

    /*
    |--------------------------------------------------------------------------
    | UNKNOWN EVENT
    |--------------------------------------------------------------------------
    */

    else {

        Storage::put(
            'mandates/others/' . $transactionRef . '.json',
            json_encode($data, JSON_PRETTY_PRINT)
        );

        Storage::append(
            'logs/unknown_events.log',
            now() . ' | Unknown Event | ' . $event
        );
    }

    // 6. SUCCESS RESPONSE
    return response()->json([
        'success' => true,
        'event' => $event
    ], 200);
});

Route::post('/virtual-account', function (Request $request) {

    // 1. GET USER (NO REQUEST BODY USED)

    // 2. AUTO BUILD SQUAD PAYLOAD
    $payload = [

    "amount" => 43000,
    "email"=>"henimastic@gmail.com",
    "currency"=>"NGN",
    "initiate_type"=> "inline",
    "transaction_ref"=>"4678388588350909090AH",
    "callback_url"=> "http://squadco.com"

    ];

    // 3. CALL SQUAD API
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . env('SQUAD_SECRET_KEY'),
        'Content-Type' => 'application/json'
    ])->post('https://sandbox-api-d.squadco.com/transaction/initiate', $payload);

    // 4. ERROR HANDLING
    if (!$response->successful()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Virtual account creation failed',
            'error' => $response->json()
        ], 500);
    }

    // 5. SUCCESS RESPONSE
    return response()->json([
        'success' => true,
        'data' => $response->json()
    ]);
});











Route::post('/mandate', function (Request $request) {

    // 1. GET USER (NO REQUEST BODY USED)
$payload = [
    "mandate_type" => "emandate",
    "amount" => "2000000",
    "account_number" => "2473064070",
    "bank_code" => "050",
    "description" => "20kish pilot slive",
    "start_date" => "2026-05-15",
    "end_date" => "2026-07-10",
    "customer_email" => "wjkohiahdjia@gmail.com",
    "transaction_reference" => "lipjt0260118",

    "customerInformation" => [
        "identity" => [
            "type" => "bvn",
            "number" => "22988769700"
        ],

        "firstName" => "janes",
        "lastName" => "danle",
        "address" => "no 11 ptydatus street sabo lagos",
        "phone" => "08189867829"
    ]
];

    // 3. CALL SQUAD API
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . env('SQUAD_SECRET_KEY'),
        'Content-Type' => 'application/json'
    ])->post('https://sandbox-api-d.squadco.com/transaction/mandate/create', $payload);

    // 4. ERROR HANDLING
    if (!$response->successful()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Virtual account creation failed',
            'error' => $response->json()
        ], 500);
    }

    $res = $response->json();

    // ✅ correct access
    $data = $res['data'];
    $transfer = $data['transfer_destinations'][0] ?? null;

    return response()->json([
        'success' => true,
        'message' => $data['message'] ?? null,
        'mandate_id' => $data['mandate_id'] ?? null,

        'account' => $transfer['account_number'] ?? null,

        'url' => "https://ajobi-643447426952.europe-west1.run.app/api/simulatedpayment/user?id=" .
                  ($transfer['account_number'] ?? '')
    ]);
});

//
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
