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

class EscrowController extends Controller
{
public function createEscrow(Request $request)
{
$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));

if (env('REV_APP_KEY') == $accessTokennewinfo) {

// VALIDATION
$validator = Validator::make($request->all(), [
'creator_id' => 'required',
'type' => 'required',
'counterparty_id' => 'required',
'amount' => 'required|numeric|min:1',
'description' => 'required|string',
'expected_completion_date' => 'nullable|date',
'listing_id' => 'nullable'
]);

if ($validator->fails()) {
return response()->json([
'success' => false,
'message' => $validator->errors()->first()
]);
}

// IDS
$escrowId = 'esc_' . uniqid();
$paymentReference = 'ESC_' . strtoupper(uniqid());
$squadPaymentLink = "https://checkout.squadco.com/" . $paymentReference;

// GET USERS
$creator = DB::table('users')->where('user_id', $request->creator_id)->first();
$counterparty = DB::table('users')->where('user_id', $request->counterparty_id)->first();

if (!$creator || !$counterparty) {
return response()->json([
'success' => false,
'message' => 'User not found'
]);
}

// AJO SCORES
$creatorScore = $creator->ajo_score ?? 0;
$counterpartyScore = $counterparty->ajo_score ?? 0;

// TRUST SCORE LOGIC
if ($counterpartyScore > 80 && $creatorScore > 80) {
$trust_score = 85;
} elseif ($counterpartyScore > 60 && $creatorScore > 60) {
$trust_score = 65;
} elseif ($counterpartyScore > 50 && $creatorScore > 50) {
$trust_score = 45;
} else {
$trust_score = 30;
}

$trust_verdict = $trust_score >= 75 ? "SAFE" : "RISKY";

// SAVE ESCROW
DB::table('escrows')->insert([
'escrow_id' => $escrowId,
'creator_id' => $request->creator_id,
'counterparty_id' => $request->counterparty_id,
'type' => $request->type,
'amount' => $request->amount,
'description' => $request->description,
'expected_completion_date' => $request->expected_completion_date,
'listing_id' => $request->listing_id,
'payment_reference' => $paymentReference,
'status' => 'pending_funding',
"creator_confirmed" => true,
'trust_score' => $trust_score,
'created_at' => now()
]);

// RESPONSE (MERGED FORMAT)
return response()->json([
'success' => true,
'message' => 'Escrow created',
'data' => [
'escrow_id' => $escrowId,
'payment_reference' => $paymentReference,
'squad_payment_link' => $squadPaymentLink,

// optional extra intelligence (kept from your system)
'type' => $request->type,
'amount' => $request->amount,
'trust_score' => $trust_score,
'trust_verdict' => $trust_verdict,
'trust_reason' => "System evaluated both parties using AjoScore"
]
]);

} else {
return response()->json([
'success' => false,
'error' => [
'code' => 'UNAUTHORIZED',
'message' => 'Token is invalid'
]
]);
}
}


public function getMyEscrows(Request $request, $userId)
{
$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));

if (env('REV_APP_KEY') == $accessTokennewinfo) {

$type = $request->query('type');
$status = $request->query('status');

$query = DB::table('escrows')
->where(function ($q) use ($userId) {
$q->where('creator_id', $userId)
->orWhere('counterparty_id', $userId);
});

if ($type && $type != 'all') {
$query->where('type', $type);
}

if ($status && $status != 'all') {
$query->where('status', $status);
}

$escrows = $query->get();

$result = [];

foreach ($escrows as $e) {

$counterparty = DB::table('users')
->where('user_id', $e->counterparty_id)
->first();

$myRole = ($e->creator_id == $userId) ? "creator" : "worker";

$result[] = [
'escrow_id' => $e->escrow_id,
'type' => $e->type,
'amount' => $e->amount,
'status' => $e->status,
'my_role' => $myRole,
'counterparty_name' => $counterparty->full_name ?? null,
'counterparty_score' => $counterparty->ajo_score ?? 0,
'description' => $e->description,
'created_at' => $e->created_at
];
}

return response()->json([
'success' => true,
'data' => [
'escrows' => $result,
'total' => count($result)
]
]);

} else {
return response()->json([
'success' => false,
'error' => [
'code' => 'UNAUTHORIZED',
'message' => 'Token is invalid'
]
]);
}
}
public function getEscrowDetail(Request $request, $escrowId)
{
$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));

if (env('REV_APP_KEY') == $accessTokennewinfo) {

$escrow = DB::table('escrows')
->where('escrow_id', $escrowId)
->first();

if (!$escrow) {
return response()->json([
'success' => false,
'message' => 'Escrow not found'
]);
}

$creator = DB::table('users')->where('user_id', $escrow->creator_id)->first();
$counterparty = DB::table('users')->where('user_id', $escrow->counterparty_id)->first();
if ($escrow->counterparty_confirmed == true && $escrow->creator_confirmed == true){
$bothconfirmed = true
}else{
$bothconfirmed = false
}
return response()->json([
'success' => true,
'data' => [
'escrow_id' => $escrow->escrow_id,
'type' => $escrow->type,
'amount' => $escrow->amount,
'status' => $escrow->status,
'description' => $escrow->description,
'trust_score' => $escrow->trust_score,
'creator' => [
'user_id' => $creator->user_id ?? null,
'name' => $creator->full_name ?? null,
'ajo_score' => $creator->ajo_score ?? 0,
'role' => 'creator'
],
'counterparty' => [
'user_id' => $counterparty->user_id ?? null,
'name' => $counterparty->full_name ?? null,
'ajo_score' => $counterparty->ajo_score ?? 0,
'role' => 'worker'
],
"confirmation_status"=>[
"creator_confirmed"=> $escrow->creator_confirmed,
"counterparty_confirmed" => $escrow->counterparty_confirmed,
"both_confirmed"=> $bothconfirmed
], 
"dispute_raised"=>  $escrow -> dispute_raised,
'created_at' => $escrow->created_at
]
]);

} else {
return response()->json([
'success' => false,
'error' => [
'code' => 'UNAUTHORIZED',
'message' => 'Token is invalid'
]
]);
}
}

public function confirmEscrow(Request $request, $escrowId)
{
$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));

if (env('REV_APP_KEY') == $accessTokennewinfo) {

$validator = Validator::make($request->all(), [
'confirmed' => 'required|boolean'
]);

if ($validator->fails()) {
return response()->json([
'success' => false,
'message' => $validator->errors()->first()
]);
}

$escrow = DB::table('escrows')->where('escrow_id', $escrowId)->first();
$ecrowconterpart_confirmed =  $escrow->counterparty_confirmed;
if ($ecrowconterpart_confirmed == true &&  $request->confirmed == true){
$bothconfirmed = true
$edit_score = DB::table('users')-> where(['user_id' => $escrow->creator_id])-> first();
$ajo_score = $edit_score -> ajo_score;
$new_ajo_score = $ajo_score + 3
DB::table('users')-> where(['user_id' => $escrow->creator_id])->update([
'ajo_score' =>  $new_ajo_score                                                    
]);
}else{
$bothconfirmed = false
}




if (!$escrow) {
return response()->json([
'success' => false,
'message' => 'Escrow not found'
]);
}

$bothConfirmed = true; // placeholder

return response()->json([
'success' => true,
'data' => [
'your_confirmation' => $request->confirmed,
'counterparty_confirmation' => $ecrowconterpart_confirmed,
'both_confirmed' => $bothconfirmed,
'payment_released' => $bothConfirmed,
'amount_released' => $escrow->amount,
'score_update' => [
'your_score_change' => 3,
'new_score' => $new_ajo_score,
'reason' => 'Escrow completed without dispute'
]
]
]);

} else {
return response()->json([
'success' => false,
'error' => [
'code' => 'UNAUTHORIZED',
'message' => 'Token is invalid'
]
]);
}
}    


    
//stop
}
