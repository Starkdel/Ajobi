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
class AuthController extends Controller
{
//
public function login(Request $request){
$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){

$validator = Validator::make($request->all(), [

'email' => 'required|email',

'password' => 'required',


]);  

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'fail'
]);
}else{
$password = $request ->password;
$email = $request -> email;
$checkemail = DB::table('users')->where(['email' => $email,'verification' => 'true'])-> count();
if($checkemail == 1){
$userdata= DB::table('users')->where(['email' => $email,'verification' => 'true'])->first();
$existingpassword = $userdata -> password;

if(md5($password) == $existingpassword){
return response()->json([
'success' => true,
'data' => [
'user_id' => $userdata->user_id, 
'full_name' => $userdata->full_name,
'token' => $userdata -> verify_token, 
'ajo_score' => $userdata->ajo_score,
'score_tier' => $userdata->score_tier,
'onboarding_complete' => $userdata -> onboarding_complete
]
]);
}else{
return response()->json([
'message' => "invalid password",
'status' => 'fail'
]);
}

}else{
return response()->json([
'message' => 'This email does not exist',
'status' => 'fail'
]);
}


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


public function verifyAccount($token)
{
$user = DB::table('users')
->where(['verify_token' => $token])
->first();

if (!$user) {
return response()->json([
'status' => 'fail',
'message' => 'Invalid or expired token.'
]);
}

DB::table('users')
->where(['verify_token' => $token])
->update([
'verification' => 'true',

]);

return redirect('https://ajobi.onrender.com/setup');
}

public function register(Request $request){

 $d_token = $request->header('Authorization');
    $accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
     if(env('REV_APP_KEY') == $accessTokennewinfo){
$validator = Validator::make($request->all(), [
'full_name' => 'required',
'email' => 'required|email|unique:users',
'phone' => 'required|string|min:10|max:15',
'password' => 'required|min:6'

]);  

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'error'
]);
}else{

$password = $request -> password;
$mobile = $request -> phone;
$email =  $request->email;

$username = $request->full_name; 

$checkemail = DB::table('users')->where(['email' => $email])-> count();
if($checkemail == 1 ){
return response()->json([
'message' => 'This Email Address already exists.',
'status' => 'error'
]);
}
$token = Str::random(64);
$userid = uniqid('user', true);
DB::table('users')->insert([
'full_name' => $request->full_name,
'phone' => $request->phone,
'email' => $request->email,
'password' => md5($request->password),
'verification' => 'false',
'verify_token' => $token,
'Date' => now(),
'user_id' => $userid,
'email_verified_at' => now(),                      
'onboarding_complete' => 'false'                   

]);

$verification_link = url('/verify-account/' . $token);

$user_d = [
'name' => $request->full_name,
'title' => 'Account Verification',
'sender_email' => 'soyombotomiwa@gmail.com',
'email' => $request->email,
'app_name' => 'AjoBI',
'verification_link' => $verification_link
];

MailController::send_mail($user_d, 'link');

return response()->json([
'status' => 'success',
'message' => 'Verification email sent.'
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


public function redirect()
{
return Socialite::driver('google')->stateless()->redirect();
}


public function callback()
{
try {
// Get the user information from Google
$user = Socialite::driver('google')->stateless()->user();
} catch (Throwable $e) {
return redirect('/')->with('error', 'Google authentication failed.');
}

// Check if the user already exists in the database
$existingUser = DB::table('users')->where(['email' => $user -> email,'verification' => 'true'])-> first();

if ($existingUser) {
// Log the user in if they already exist
return response()->json([
'res' => 'oldloggedin'
]);
} else {
// Otherwise, create a new user and log them in
$newUser = DB::table('users')-> insert([
'email' => $user->email,
'name' => $user->name,
'verification'=> 'true',
'email_verified_at' => now()
]);
return response()->json([
'res' => 'loggedin'
]);
}


}
public function onboardingstep1(Request $request){
 $d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){   
$validator = Validator::make($request->all(), [
'occupation' => 'required',
'email'  => 'required|email',                           
]);  

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'fail'
]);
}else{
$occupation = $request -> occupation;
$email = $request -> email;
$data = DB::table('users')
->where(['email' => $email])
->update([
'occupation' => $occupation,

]);
if($data){
return response()->json([
"success" => true,
"data" => [
"step_completed"=> "1",
"next_step"=> "2"

]


]);

}else{
return response()->json([
"success" => false,
"data" => [
"step_completed"=> "null",
"next_step"=> "1"

]


]);
}


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


public function onboardingstep2(Request $request){
    $d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
$validator = Validator::make($request->all(), [
'trade_duration' => 'required',
'state' => 'required',
'lga' => 'required',
'income_range' => 'required',
'email'  => 'required|email',                           
]);  

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'fail'
]);
}else{
$income_range = $request -> income_range;
$lga = $request -> lga;
$state = $request -> state;
$trade_duration = $request -> trade_duration;

$email = $request -> email;
$data = DB::table('users')
->where(['email' => $email])
->update([
'income_range' => $income_range,
'lga' => $lga,
'state' => $state,
'trade_duration' => $trade_duration,
]);
if($data){
return response()->json([
"success" => true,
"data" => [
"step_completed"=> "2",
"next_step"=> "3"

]


]);

}else{
return response()->json([
"success" => false,
"data" => [
"step_completed"=> "1",
"next_step"=> "2"

]


]);
}


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

public function onboardingstep3(Request $request){
    $d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
$validator = Validator::make($request->all(), [
'saves_money' => 'required|boolean',
'savings_methods' => 'required|array',
'savings_methods.*' => 'string',
'in_ajo_group' => 'required|boolean',
'contribution_consistency' => 'required',
'email'  => 'required|email',                           
]);  

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'fail'
]);
}else{
$contribution_consistency = $request -> contribution_consistency;
$in_ajo_group = $request -> in_ajo_group;
$saves_money = $request -> saves_money;
$email = $request -> email;
$information = $validator->validated();  
$data = DB::table('users')
->where(['email' => $email])
->update([
'contribution_consistency' => $contribution_consistency,
'in_ajo_group' => $in_ajo_group,
'saves_money' => $saves_money,
'savings_methods' => $information['savings_methods'],
]);
if($data){
return response()->json([
"success" => true,
"data" => [
"step_completed"=> "3",
"next_step"=> "4"

]


]);

}else{
return response()->json([
"success" => false,
"data" => [
"step_completed"=> "2",
"next_step"=> "3"

]


]);
}


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


public function onboardingstep4(Request $request){
    $d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
$validator = Validator::make($request->all(), [
'repaid_on_time' => 'required|boolean',
'repaid_fully' => 'required|boolean',
'has_borrowed' => 'required|boolean',
'email'  => 'required|email',                           
]);  

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'fail'
]);
}else{
$repaid_on_time = $request -> repaid_on_time;
$repaid_fully = $request -> repaid_fully;
$has_borrowed = $request -> has_borrowed;
$email = $request -> email;
$data = DB::table('users')
->where(['email' => $email])
->update([
'repaid_on_time' => $repaid_on_time,
'repaid_fully' => $repaid_fully,
'has_borrowed' => $has_borrowed
]);
if($data){
return response()->json([
"success" => true,
"data" => [
"step_completed"=> "4",
"next_step"=> "5"

]


]);

}else{
return response()->json([
"success" => false,
"data" => [
"step_completed"=> "3",
"next_step"=> "4"

]


]);
}


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

public function onboardingcheck(Request $request){
 $d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){ 
$validator = Validator::make($request->all(), [
'email'  => 'required|email',                           
]);  

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'fail'
]);
}else{
    $email = $request -> email;
$data = DB::table('users')
->where(['email' => $email])
->first();
$language = $data -> language;
$state = $data -> state;
    $trade_duration = $data -> trade_duration;
    $saves_money= $data -> saves_money;
    $contribution_consistency = $data -> contribution_consistency;
 $repaid_on_time = $data -> repaid_on_time;
    $repaid_fully = $data -> repaid_fully;
$occupation = $data -> occupation;

if($occupation == NULL){
return response()->json([
 "success"=> true,
  "data" => [
    "steps_completed"=> "NONE",
    "current_step"=> 1,
    "onboarding_complete"=> false
  ]

]);
}else if($state == NULL || $trade_duration == NULL) {
return response()->json([
 "success"=> true,
  "data" => [
    "steps_completed"=> [1],
    "current_step"=> 2,
    "onboarding_complete"=> false
  ]

]);
}else if($saves_money == NULL || $contribution_consistency == NULL) {
return response()->json([
 "success"=> true,
  "data" => [
    "steps_completed"=> [1,2],
    "current_step"=> 3,
    "onboarding_complete"=> false
  ]

]);
}else if($repaid_fully == NULL || $repaid_on_time == NULL) {
return response()->json([
 "success"=> true,
  "data" => [
    "steps_completed"=> [1,2,3],
    "current_step"=> 4,
    "onboarding_complete"=> false
  ]

]);
}else if($language == NULL) {
return response()->json([
 "success"=> true,
  "data" => [
    "steps_completed"=> [1,2,3,4],
    "current_step"=> 5,
    "onboarding_complete"=> false
  ]

]);
}else{
return response()->json([
 "success"=> true,
  "data" => [
    "steps_completed"=> [1,2,3,4,5],
    "current_step"=> "completed",
    "onboarding_complete"=> true
  ]

]);
}
    
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
