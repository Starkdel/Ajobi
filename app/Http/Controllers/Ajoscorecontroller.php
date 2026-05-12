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

$validator = Validator::make($request->all(), [

'email' => 'required|email',
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


return response()->json([

"success" => true,

"data" => [

"onboarding_complete" => true,

"ajo_score" => $finalScore,

"score_tier" => $tier,

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
}









//stop
}



