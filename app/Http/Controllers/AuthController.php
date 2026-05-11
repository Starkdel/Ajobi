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
