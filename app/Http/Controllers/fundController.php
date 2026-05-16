<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\MailController;
use Nette\Utils\Random;
use Laravel\Socialite\Facades\Socialite;
class fundController extends Controller
{

public function webhook(Request $request)
{
  // 🔐 RAW BODY
    $rawPayload = $request->getContent();

    // 🔐 SECRET KEY (same as your PHP example)
    $secretKey = env('SQUAD_SECRET_KEY');

    // 🔐 GET SIGNATURE FROM HEADER
    $expectedSignature = $request->header('x-signature'); 
    // (change this header name if Squad uses another one)

    /**
     * 🔐 GENERATE HMAC-SHA512 FROM RAW BODY
     * IMPORTANT: must match EXACT format sent by Squad
     */
    $generatedSignature = hash_hmac('sha512', $rawPayload, $secretKey);

    // ❌ INVALID REQUEST
    if ($generatedSignature !== $expectedSignature) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid signature'
        ], 401);
    }

    // ✅ PARSE JSON AFTER VERIFICATION
    $data = json_decode($rawPayload, true);

    $event = $data['Event'] ?? null;
    $transactionRef = $data['TransactionRef'] ?? null;
    $body = $data['Body'] ?? [];

    /**
     * 💾 STORE IN DATABASE (ALL WEBHOOK TYPES)
     */
    DB::table('webhook_logs')->insert([
        'event' => $event,
        'transaction_ref' => $transactionRef,
        'transaction_type' => $body['transaction_type'] ?? null,
        'status' => $body['transaction_status'] ?? null,
        'amount' => $body['amount'] ?? null,
        'email' => $body['email'] ?? null,
        'payload' => json_encode($data),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Webhook verified and stored'
    ]);
}

public function martketplace(Request $request, $listingId)
{
    $d_token = $request->header('Authorization');
    $accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));

    if (env('REV_APP_KEY') != $accessTokennewinfo) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Token is invalid'
            ]
        ]);
    }

    $validator = Validator::make($request->all(), [
        'user_id' => 'required',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => $validator->errors()->first(),
            'status' => 'error'
        ]);
    }

    $user_id = $request->user_id;

    $listingdetails = DB::table('listings')
        ->where('listing_id', $listingId)
        ->first();

    $creator_id = $listingdetails->user_id;
    $price = $listingdetails->price;

    $user = DB::table('users')->where('user_id', $user_id)->first();
    $seller = DB::table('users')->where('user_id', $creator_id)->first();

    DB::table('transaction')->insert([
        'buyer' => $user->full_name,
        'seller' => $seller->full_name,
        'price' => $price,
        'status' => 'pending',
        'type' => 'marketplace',
        'transaction_type' => 'market',
        'category' => $listingdetails->category,
        'title' => $listingdetails->title,
        'buyer_id' => $user_id,
        'seller_id' => $creator_id,
    ]);

    return response()->json([
        'status' => 'success',
        'virtual_account' => $seller->virtual_account,
    ]);
}

public function virtual_account(Request $request) {
$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
$validator = Validator::make($request->all(), [
'user_id' => 'required',

]);

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'error'
]);
}
$dob = \Carbon\Carbon::now()
->subYears(rand(18, 60))
->subDays(rand(0, 365))
->format('m/d/Y');
$uniqueId = uniqid('CUS', true);
$user = DB::table('users')
->where('user_id', $request -> user_id)
->first();
$payload = [
"customer_identifier" =>  $uniqueId,
"first_name" => $user->full_name,
"last_name" => $user->full_name,
"mobile_num" => $user->phone,
"email" => $user->email,
"bvn" => $user->bvn,
"dob" =>$dob,
"address" => "22 Kota street, Lagos",
"gender" => "1",
"beneficiary_account" => $user->beneficiary_account
];
// 🌐 CALL SQUAD API
$response = Http::withHeaders([
'Authorization' => env('SQUAD_SECRET_KEY'),
'Content-Type' => 'application/json',
])->post('https://sandbox-api-d.squadco.com/virtual-account', $payload );

$data = $response->json();

    /**
$accountNumber = $data['data']['virtual_account_number'];
$customerid = $data['data']['customer_identifier'];
DB::table('users')-> where('user_id', $request -> user_id)->update(['virtual_account' => $accountNumber,
'customer_id' =>     $customerid        

]);
    **/
return response()->json([
'status' => 'success',
'data' => $response->json()
]);


}else{
return response()->json([
'success' => 'false',
'error' => [
'code' => 'UNAUTHORIZED',
'message'=> 'Token is invalid'
]
]);

}

}


public function kyc(Request $request) {
$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
$validator = Validator::make($request->all(), [
'user_id' => 'required',
 'beneficiary_account' => 'required',
 'bvn' => 'required',
'account_name' => 'required',
]);

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'error'
]);
}
$user = DB::table('users')
->where('user_id', $request -> user_id)
->update([
        'bvn' => $request -> bvn,
         'beneficiary_account' => $request -> beneficiary_account,
          'account_name' => $request -> account_name
        ]);
return response()->json([
'success' => 'true',
 'message' => 'kyc updated'
]);
    


}else{
return response()->json([
'success' => 'false',
'error' => [
'code' => 'UNAUTHORIZED',
'message'=> 'Token is invalid'
]
]);

}
}




    
public function group_virtual_account(Request $request) {
$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
$validator = Validator::make($request->all(), [
'group_id' => 'required',

]);

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'error'
]);
}
$dob = \Carbon\Carbon::now()
->subYears(rand(18, 60))
->subDays(rand(0, 365))
->format('m/d/Y');
$uniqueId = uniqid('CUS', true);
$user = DB::table('groups')
->where('group_id', $request -> group_id)
->first();
$creator_id = $user -> creator_id;
$number = '081' . rand(10000000, 99999999);
$group_email =  DB::table('users')
->where('user_id', $creator_id)
->first();
$payload = [
"customer_identifier" =>  $uniqueId,
"first_name" => $user->name,
"last_name" => $user->name,
"mobile_num" => $number,
"email" => $group_email -> email,
"bvn" => "22343213984",
"dob" =>$dob,
"address" => "22 Kota street, Lagos",
"gender" => "1",
"beneficiary_account" => "4920949492" // squad wallet (i.e no beneficiay)
];
// 🌐 CALL SQUAD API
$response = Http::withHeaders([
'Authorization' => env('SQUAD_SECRET_KEY'),
'Content-Type' => 'application/json',
])->post('https://sandbox-api-d.squadco.com/virtual-account', $payload );

$data = $response->json();

    /**
$accountNumber = $data['data']['virtual_account_number'];
$customerid = $data['data']['customer_identifier'];
DB::table('groups')->where('group_id', $request -> group_id)->update(['virtual_account' => $accountNumber,
'customer_id' =>     $customerid        

]);
    **/
return response()->json([
'status' => 'success',
'data' => $response->json()
]);


}else{
return response()->json([
'success' => 'false',
'error' => [
'code' => 'UNAUTHORIZED',
'message'=> 'Token is invalid'
]
]);

}

}


public function group_payment(Request $request) {
$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
$validator = Validator::make($request->all(), [
'user_id' => 'required',
'group_id' => 'required',

]);

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'error'
]);
}

$details = DB::table('groups')->where('group_id', $request -> group_id)->first();
    $account = $details-> virtual_account;
$params = [
    'account_number' => $account,
    'user_id' => $request -> user_id,
    'group_id' => $request -> group_id,
    "amount" => $details -> contribution_amount,
];

$url = 'https://ajobi-643447426952.europe-west1.run.app/api/simulatepayment/group?' 
     . http_build_query($params);

    
return response()->json([
'success' => 'true',
 'message' => "payment initiated",
 'url' => $url
]);
    


}else{
return response()->json([
'success' => 'false',
'error' => [
'code' => 'UNAUTHORIZED',
'message'=> 'Token is invalid'
]
]);

}
}


    


    
public function create_escrow_virtual_account(Request $request) {
$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
$validator = Validator::make($request->all(), [
'escrow_id' => 'required',

]);

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'error'
]);
}
$dob = \Carbon\Carbon::now()
->subYears(rand(18, 60))
->subDays(rand(0, 365))
->format('m/d/Y');
$uniqueId = uniqid('CUS', true);
$user = DB::table('escrows')
->where('escrow_id', $request -> escrow_id)
->first();
$creator_id = $user-> creator_id;
$escrow_name = $request -> escrow_id;
$number = '081' . rand(10000000, 99999999);
$escrow_email =  DB::table('users')
->where('user_id', $creator_id)
->first();
$payload = [
"customer_identifier" =>  $uniqueId,
"first_name" => $escrow_name,
"last_name" => $escrow_name,
"mobile_num" => $number,
"email" => $escrow_email-> email,
"bvn" => "29843213984",
"dob" =>$dob,
"address" => "22 Kota street, Lagos",
"gender" => "1",
"beneficiary_account" => "2020949492" // squad wallet (i.e no beneficiay)
];
// 🌐 CALL SQUAD API
$response = Http::withHeaders([
'Authorization' => env('SQUAD_SECRET_KEY'),
'Content-Type' => 'application/json',
])->post('https://sandbox-api-d.squadco.com/virtual-account', $payload );

$data = $response->json();
/**
$accountNumber = $data['data']['virtual_account_number'];
$customerid = $data['data']['customer_identifier'];
DB::table('escrows')->where('escrow_id', $request -> escrow_id)->update(['virtual_account' => $accountNumber,
'customer_id' =>     $customerid        

]);
    **/
return response()->json([
'status' => 'success',
'data' => $response->json()
]);


}else{
return response()->json([
'success' => 'false',
'error' => [
'code' => 'UNAUTHORIZED',
'message'=> 'Token is invalid'
]
]);

}

}



     
public function Escrow_disbursement(Request $request) {
$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
$validator = Validator::make($request->all(), [
'escrow_id' => 'required',

]);

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'error'
]);
}


$details = DB::table('escrows')->where('escrow_id', $request -> escrow_id)->first();
    $creator_confirmed = $details-> creator_confirmed;
  $counterparty_confirmed = $details-> counterparty_confirmed;
      $counterparty_id = $details-> counterparty_id;
    $url = null;
    if( $creator_confirmed == "true" && $counterparty_confirmed == true){
 $userdetails = DB::table('users')->where('user_id', $counterparty_id)-> first();
        $params = [
    'account_number' => $userdetails -> virtual_account,
    'user_id' => $counterparty_id ,
    "amount" => $details -> amount,
            "escrow_id" =>  $request -> escrow_id,
];

$url = 'https://ajobi-643447426952.europe-west1.run.app/api/simulatepayment/escrow?' 
     . http_build_query($params);

return response()->json([
'success' => 'true',
 'message' => "payment initiated",
 'url' => $url
]);
    


    }else{
return response()->json([
'success' => 'false',
 'message' => "agreement not yet reached",
 'url' => $url
]);
    
    }

    



}else{
return response()->json([
'success' => 'false',
'error' => [
'code' => 'UNAUTHORIZED',
'message'=> 'Token is invalid'
]
]);

}
}
  
//stop
}
