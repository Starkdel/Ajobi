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
'message' => "User LoggedIn",
'status' => 'success'
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
}


public function verifyAccount($token)
{
    $user = DB::table('users')
        ->where('verify_token', $token)
        ->first();

    if (!$user) {
        return response()->json([
            'status' => 'fail',
            'message' => 'Invalid or expired token.'
        ]);
    }

    DB::table('users')
        ->where('id', $user->id)
        ->update([
            'verification' => true,

        ]);

    return redirect('/dashboard');
}

public function register(Request $request){


$validator = Validator::make($request->all(), [
'full_name' => 'required',
'email' => 'required|email|unique:users',
'phone' => 'required|string|min:10|max:15',
'password' => 'required|min:6'

]);  

if ($validator->fails()) {
return response()->json([
'message' => $validator->errors()->first(),
'status' => 'fail'
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
'status' => 'fail'
]);
}
 $token = Str::random(64);
$userid = uniqid('user', true);
    DB::table('users')->insert([
        'full_name' => $request->full_name,
        'phone' => $request->phone,
        'email' => $request->email,
        'password' => md5($request->password),
        'verification' => false,
        'verify_token' => $token,
        'Date' => now(),
        'user_id' => $userid,
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


}
