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

public function creategroup(Request $request)
{

$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
 $validator = Validator::make($request->all(), [

        'name' => 'required|string|max:255',

        'contribution_amount' => 'required|numeric|min:1',

        'frequency' => 'required|in:weekly,monthly',

        'max_members' => 'required|integer|min:5|max:20',

        'min_ajo_score' => 'required|integer|min:40|max:100',

        'rotation_type' => 'required|in:random,manual',

        'grace_period_hours' => 'required|in:24,48',

        'description' => 'required|string'

    ]);

    
    if ($validator->fails()) {

        return response()->json([
            "success" => false,
            "message" => $validator->errors()->first(),
        ]);

    }

    // Generate Group ID
    $groupId = 'grp_' . uniqid();

    // Generate Invite Code
    $inviteCode = strtoupper(substr($request->name, 0, 3)) . "-" . rand(1000,9999);

    // Invite Link
    $inviteLink = "https://ajobi-643447426952.europe-west1.run.app/api/" . $inviteCode;

    // Insert into DB
    DB::table('groups')->insert([

        'group_id' => $groupId,

        'name' => $request->name,

        'contribution_amount' => $request->contribution_amount,

        'frequency' => $request->frequency,

        'max_members' => $request->max_members,

        'min_ajo_score' => $request->min_ajo_score,

        'rotation_type' => $request->rotation_type,

        'grace_period_hours' => $request->grace_period_hours,

        'description' => $request->description,

        'invite_code' => $inviteCode,

        'status' => 'awaiting_members',

        'created_at' => now()

    ]);

    return response()->json([

        "success" => true,

        "data" => [

            "group_id" => $groupId,

            "name" => $request->name,

            "invite_link" => $inviteLink,

            "invite_code" => $inviteCode,

            "status" => "awaiting_members",

            "created_at" => now()

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
