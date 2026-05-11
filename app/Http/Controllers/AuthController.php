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
