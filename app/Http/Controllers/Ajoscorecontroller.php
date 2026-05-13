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
DB::table('ajoscorecalculation')->insert([
'email' => $email,
"savings_consistency" => $savings_score,

"repayment_behaviour" => $repayment_score,

"escrow_completion" => $escrow_completion,

"transaction_history" => $transaction_history,

"account_maturity" => $account_maturity,

"community_standing" => $community_standing,
"Date" => now()
]);


return response()->json([

"success" => true,

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









//stop
}



