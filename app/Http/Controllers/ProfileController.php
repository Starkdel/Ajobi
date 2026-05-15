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
class ProfileController extends Controller
{
//

public function getProfile($userId)
{
   $d_token = $request->header('Authorization');
    $accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
     if(env('REV_APP_KEY') == $accessTokennewinfo){
    $user = DB::table('users')->where('user_id', $userId)->first();

    if (!$user) {
        return response()->json([
            "success" => false,
            "message" => "User not found"
        ], 404);
    }

    return response()->json([
        "success" => true,
        "data" => [
            "user_id" => $user->id,
            "full_name" => $user->name,
            "phone" => $user->phone,
            "email" => $user->email,
            "occupation" => $user->occupation,
            "state" => $user->state,
            "lga" => $user->lga,
            "language" => $user->language,
            "profile_photo" => $user->profile_photo ?? null,
            "member_since" => $user->Date,
            "ajo_score" => $user->ajo_score,
            "score_tier" => $user->score_tier,

            // 👇 JSON skills decoded
            "skills" => json_decode($user->skills ?? "[]"),

            "availability" => $user->availability ?? null,
            "rate" => $user->rate ?? null,
            "referral_code" => $user->referral_code ?? null,
            "referral_count" => $user->referral_count ?? null,
            "referral_score_bonus" => $user->referral_score_bonus ?? null,
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
