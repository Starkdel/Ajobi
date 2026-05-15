<?php

namespace App\Http\Controllers;
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
$payload = $request->getContent();
$signature = $request->header('x-squad-encrypted-body');

$secret = env('SQUAD_SECRET_KEY');

$calculatedSignature = strtoupper(hash_hmac('sha512', $payload, $secret));

if ($signature !== $calculatedSignature) {
return response()->json(['message' => 'Invalid signature'], 401);
}

$data = json_decode($payload, true);

if (!$data) {
return response()->json(['message' => 'Invalid payload'], 400);
}

$body = $data['Body'] ?? [];

// STORE USING DB::
DB::table('webhook_logs')->insert([
'event' => $data['Event'] ?? null,
'transaction_ref' => $data['TransactionRef'] ?? null,
'transaction_type' => $body['transaction_type'] ?? null,
'status' => $body['transaction_status'] ?? null,
'amount' => $body['amount'] ?? null,
'email' => $body['email'] ?? null,
'payload' => json_encode($data), // store full JSON
'created_at' => now(),
'updated_at' => now(),
]);



return response()->json(['message' => 'Webhook stored'], 200);
}


public function martketplace(Request $request, $listingId)
{

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
}else{
$user_id = $request->user_id;
$listingdetails = DB::table('listings')->where(['listing_id' => $listingId])-> first();
$creator_id = $listingdetails -> user_id;
$price = $listingdetails -> price;  
$user = DB::table('users')
->where('user_id', $user_id)
->first();
$seller = DB::table('users')
->where('user_id', $creator_id)
->first();


DB::table('transaction')->insert([

'buyer'=> $user -> full_name,
'seller'=>  $seller -> full_name,
'price' =>   $price,
'status' => 'pending',
'type' => 'marketplace',
'transaction_type' => 'market',
"category"  => $listingdetails -> category;
"title"  => $listingdetails -> title;
'buyer_id'=> $user -> $user_id,
'seller_id'=>  $seller -> $creator_id,
]);



return  response()-> json([
'status' => 'success',
'virtual_account' => $seller -> virtual_account,

]);
else{
return response()->json([
'success' => 'false',
'error' => [
'code' => 'UNAUTHORIZED',
'message'=> 'Token is invalid'
]
]);

}
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

$accountNumber = $data['data']['virtual_account_number'];
$customerid = $data['data']['customer_identifier'];
DB::table('users')-> where('user_id', $request -> user_id)->update(['virtual_account' => $accountNumber,
'customer_id' =>     $customerid        

]);
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
         'beneficiary_account' => $request -> beneficiary_account
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
//stop
}
