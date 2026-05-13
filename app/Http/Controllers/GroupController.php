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
        'user_id' => 'required',
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
    //include groupid as user details
$user = DB::table('group_members')
    ->insert([
'creator_id' => $request->user_id,
 'group_id' => $groupId,
 
             
             ]);


$groupjoined = json_decode($user->groupcreatorjoined , true) ?? [];

if (in_array($groupId, $groupjoined)) {
    return response()->json([
        "success" => false,
        "message" => "You have already joined this group"
    ]);
}

// add only if not exists
$groupjoined[] = $groupId;

DB::table('users')
    ->where('user_id', $request->user_id)
    ->update([
        'groupcreatorjoined ' => json_encode($groupjoined)
    ]);

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
    public function browsegroup(Request $request)
{

$d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
  $frequency = $request->query('frequency');
    $min = $request->query('min_amount');
    $max = $request->query('max_amount');
    $page = $request->query('page', 1);
    $limit = $request->query('limit', 10);

    $query = DB::table('groups');

    if ($frequency) {
        $query->where('frequency', $frequency);
    }

    if ($min) {
        $query->where('contribution_amount', '>=', $min);
    }

    if ($max) {
        $query->where('contribution_amount', '<=', $max);
    }

    $total = $query->count();

    $groups = $query
        ->offset(($page - 1) * $limit)
        ->limit($limit)
        ->get();

    $result = [];

    foreach ($groups as $group) {

        $currentMembers = DB::table('group_members')
            ->where('group_id', $group->group_id)
            ->count();

        $creator = DB::table('users')
            ->where('user_id', $group->creator_id)
            ->first();

        $result[] = [
            "group_id" => $group->group_id,
            "name" => $group->name,
            "contribution_amount" => $group->contribution_amount,
            "frequency" => $group->frequency,
            "current_members" => $currentMembers,
            "max_members" => $group->max_members,
            "min_ajo_score" => $group->min_ajo_score,
            "creator_name" => $creator->name ?? null,
            "creator_score" => $creator->ajo_score ?? 0,
            "spots_remaining" => $group->max_members - $currentMembers,
            "next_contribution_date" => now()->addDays(7)->toISOString(),
            "tier" => "Bronze",
            "locked" => false
        ];
    }

    return response()->json([
        "success" => true,
        "data" => [
            "groups" => $result,
            "total" => $total,
            "page" => (int) $page,
            "limit" => (int) $limit
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
