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

class Ajoscorecontroller extends Controller
{
public function calculateAjoScore(Request $request)
{

$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
$validator = Validator::make($request->all(), [

'email' => 'required|email',
"language" => "required",

'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',

], [

'profile_photo.image' => 'File must be an image',

'profile_photo.mimes' => 'Images must be jpeg, png, or jpg',

'profile_photo.max' => 'Image size should be less than 5MB',

]);

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'fail'
]);
}else{


// Constants
$MAX_ONBOARDING_SCORE = 50;
$MIN_ONBOARDING_SCORE = 10;
$email = $request -> email;
// Get user from DB
$user = DB::table('users')
->where('email', $email)
->first();

// User not found
if (!$user) {
return response()->json([
'success' => false,
'message' => 'User not found'
]);
}

//upload image
$imageUrl = null;
if ($request->hasFile('profile_photo')) {

$file = $request->file('profile_photo');

$filename = time().'_'.$file->getClientOriginalName();

$path = $file->storeAs('public/images', $filename);

$imagePath = 'storage/images/'.$filename;

DB::table('users')
->where('email', $email)
->update([
'profile_photo' => $imagePath,
'language' => $request -> language
]);

$imageUrl = asset($imagePath);
}else {

    // update only language
    DB::table('users')
        ->where('email', $email)
        ->update([
            'language' => $request->language
        ]);
}

// svaings contribution

$savings_score = 0;

$savings_methods = json_decode($user->savings_methods, true) ?? [];

if ($user->saves_money == true) {

if (in_array("Ajo/Esusu group", $savings_methods)) {
$savings_score += 40;
}

if (in_array("Bank", $savings_methods)) {
$savings_score += 25;
}

if (in_array("Mobile money", $savings_methods)) {
$savings_score += 20;
}

if (in_array("Cash at home", $savings_methods)) {
$savings_score += 10;
}

if ($user->in_ajo_group == true) {
$savings_score += 30;
}

if ($user->contribution_consistency === "Always on time") {
$savings_score += 30;
}

if ($user->contribution_consistency === "Sometimes late") {
$savings_score += 15;
}

if ($user->contribution_consistency === "Often late") {
$savings_score += 0;
}
}


$savings_score = min($savings_score, 50);

// replayment contribution
$repayment_score = 0;

if (!$user->has_borrowed == true) {

$repayment_score = 25;

} elseif ($user->repaid_fully == true && $user->repaid_on_time == true) {

$repayment_score = 50;

} elseif ($user->repaid_fully == true && $user->repaid_on_time == false) {

$repayment_score = 35;

} elseif ($user->repaid_fully == false) {

$repayment_score = 10;
}


$escrow_completion = 25;

$transaction_history = 10;

$account_maturity = 0;

$community_standing = 0;

// final weighted ajorescore
$rawScore =
($savings_score * 0.25) +
($repayment_score * 0.25) +
($escrow_completion * 0.20) +
($transaction_history * 0.15) +
($account_maturity * 0.10) +
($community_standing * 0.05);

// Cap at max onboarding score
$finalScore = min(round($rawScore), $MAX_ONBOARDING_SCORE);


$finalScore = max($finalScore, $MIN_ONBOARDING_SCORE);


// tiers
$tier = "Bronze";

if ($finalScore >= 91) {
$tier = "Elite";
} elseif ($finalScore >= 76) {
$tier = "Gold";
} elseif ($finalScore >= 61) {
$tier = "Silver";
}
elseif ($finalScore >= 31) {
$tier = "Bronze";
}


// update database
DB::table('users')
->where('email', $email)
->update([
'ajo_score' => $finalScore,
'score_tier' => $tier
]);
DB::table('ajo_score_history')
->insert([
'user_id' =>$user -> user_id,
'score' => $finalScore,
"date" => now()
]);

$true = DB::table('ajoscorecalculation')->insert([
'email' => $email,
"savings_consistency" => $savings_score,

"repayment_behaviour" => $repayment_score,

"escrow_completion" => $escrow_completion,

"transaction_history" => $transaction_history,

"account_maturity" => $account_maturity,
"ajoscore" => $finalScore,
"community_standing" => $community_standing,
"user_id" => $user -> user_id,
"Date" => now()
]);

    DB::table('users')->where('email', $email)->update([
            'onboarding_complete' => "true"
        ]);
return response()->json([

"success" => true,
 "test" => $user,
"data" => [

"onboarding_complete" => true,

"ajo_score" => $finalScore,

"score_tier" => $tier,
"profile_image" => $imageUrl,

"breakdown" => [

"savings_consistency" => $savings_score,

"repayment_behaviour" => $repayment_score,

"escrow_completion" => $escrow_completion,

"transaction_history" => $transaction_history,

"account_maturity" => $account_maturity,

"community_standing" => $community_standing

],

"explanation" => "Your score is".$finalScore. "Strong savings habits detected. Score is limited by no transaction history yet. Join an Ajo group and start transacting to grow faster.",

"improvement_tips" => [

"Join an Ajo group and contribute consistently",

"Complete your first escrow transaction",

"Refer a friend to earn +3 points"

]

]

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


public function getAjoreScore (Request $request, $userId) {

$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){

$user = DB::table('users')
->where('user_id', $userId)
->first();

// User not found
if (!$user) {
return response()->json([
'success' => false,
'message' => 'User not found'
]);
}

$score = $user->ajo_score;
$tier = $user -> score_tier;
if ($score >= 91) {
$colorcode = "#6A0DAD";
$next = "Max_level";
$nexttierpoint = "max_level";
} elseif ($score >= 76) {
$colorcode = "#FFD700";
$next = "Elite";
$nexttierpoint = 91 - $score;
} elseif ($score >= 61) {
$next = "Gold";
$colorcode = "#C0C0C0";
$nexttierpoint = 76- $score;
}
elseif ($score >= 31) {
$next = "Silver";
$colorcode = "#CD7F32";
$nexttierpoint = 61 - $score;
} 
$latestdata = DB::table('ajoscorecalculation')->where('user_id', $userId)->orderBy('Date', 'desc')->first();
$savings_score = $latestdata -> savings_consistency;

$repayment_score = $latestdata ->repayment_behaviour;

$escrow_completion = $latestdata ->escrow_completion;
$transaction_history = $latestdata ->transaction_history;

$account_maturity = $latestdata ->account_maturity;

$community_standing = $latestdata ->community_standing;
$count = DB::table('ajoscorecalculation')
->where('user_id', $userId)
->where('savings_consistency', '>', 65)
->count();
$totalcount = DB::table('ajoscorecalculation')
->where('user_id', $userId)
->count();
return response()->json([
"success" => true,
"data" => [
"score" => $score,

"tier" => [
"name" => $tier,
"color" => $colorcode,
"next" => $next,
"points_to_next" => $nexttierpoint
],

"breakdown" => [
"savings_consistency" => [
"score" => $savings_score,
"weight" => 0.25,
"label" => "Savings Consistency",
"explanation" => "You have contributed on time in". $count. " of 11 cycles". $totalcount
],
"repayment_behaviour" => [
"score" => $repayment_score,
"weight" => 0.25,
"label" => "Repayment Behaviour",
"explanation" => "No loan history yet. Take and repay a loan to improve this."
],
"escrow_completion" => [
"score" => $escrow_completion,
"weight" => 0.20,
"label" => "Escrow Completion",
"explanation" => "All your escrows completed without dispute"
],
"transaction_history" => [
"score" => $transaction_history,
"weight" => 0.15,
"label" => "Transaction History",
"explanation" => "Transact more through AjoBI to improve this component"
],
"account_maturity" => [
"score" => $account_maturity,
"weight" => 0.10,
"label" => "Account Maturity",
"explanation" => "Account is 3 months old. Score grows with time."
],
"community_standing" => [
"score" => $community_standing,
"weight" => 0.05,
"label" => "Community Standing",
"explanation" => "2 successful referrals. No disputes raised against you."
]
],

"features" => [
"unlocked" => [
"ajo_groups_bronze",
"marketplace_browse",
"escrow_basic",
"instalment_escrow"
],

"locked" => [
[
"feature" => "loans",
"required_score" => 61,
"current_score" => 68,
"unlocked" => true,
"message" => "Unlocked"
],
[
"feature" => "premium_groups",
"required_score" => 76,
"current_score" => 68,
"unlocked" => false,
"points_needed" => 8,
"message" => "You need 8 more points to unlock this feature"
]
]
],

"improvement_tips" => [
"Your repayment behaviour has the most room to grow. Apply for a small loan and repay on time.",
"Increase your transaction history by making more purchases through the marketplace."
]
]
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


public function Ajohistory($userId, Request $request){
$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
$days = $request->query('days', 30);

$history = DB::table('ajo_score_history')
->where('user_id', $userId)
->where('date', '>=', now()->subDays($days))
->orderBy('date')
->get();

return response()->json([
    "success" => true,
    "data" => [
        "period_days" => (int) $days,
        "history" => $history->map(function ($item) {
            return [
                "date" => $item -> date,
                "score" => $item->score
            ];
        })->values()
    ]
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


  public function ajoevents($userId, Request $request)
    {
 $d_token = $request->header('Authorization');
    $accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
     if(env('REV_APP_KEY') == $accessTokennewinfo){

$limit = $request->query('limit', 20);
$offset = $request->query('offset', 0);

$events = DB::table('ajo_score_events')
    ->where('user_id', $userId)
    ->orderBy('created_at', 'desc')
    ->offset($offset)
    ->limit($limit)
    ->get();

$total = DB::table('ajo_score_events')
    ->where('user_id', $userId)
    ->count();

return response()->json([
    "success" => true,
    "data" => [
        "events" => $events->map(function ($item) {
            return [
                "event_id" => $item->event_id,
                "event_type" => $item->event_type,
                "points" => (int) $item->points,
                "direction" => $item->direction,
                "reason" => $item->reason,
                "created_at" => $item->created_at, // ISO format
            ];
        })->values(),

        "total" => $total,
        "limit" => (int) $limit,
        "offset" => (int) $offset
    ]
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



