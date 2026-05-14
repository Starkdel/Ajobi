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
class GroupController extends Controller
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

        'description' => 'required|string',
    //    'joining_method' => 'required'

    ]);

    
    if ($validator->fails()) {

        return response()->json([
            "success" => false,
            "message" => $validator->errors()->first(),
        ]);

    }

    // Generate Group ID
    $groupId = 'grp_' . uniqid();
/*
      if( $request -> joining_method == "automatch"){
$inviteLink = "null";
$inviteCode = "null";

    }else if( $request -> joining_method == "manual"){

    // Generate Invite Code
    $inviteCode = strtoupper(substr($request->name, 0, 3)) . "-" . rand(1000,9999);

    // Invite Link
    $inviteLink = "https://ajobi-643447426952.europe-west1.run.app/api/" . $inviteCode;
    //include groupid as user details

    }

**/

   // Generate Invite Code
    $inviteCode = strtoupper(substr($request->name, 0, 3)) . "-" . rand(1000,9999);

    // Invite Link
    $inviteLink = "https://ajobi-643447426952.europe-west1.run.app/api/" . $inviteCode;
    //include groupid as user details

    // Insert into DB
    DB::table('groups')->insert([
        'creator_id' => $request->user_id,
        'group_id' => $groupId,
         'group_members' => json_encode([
    [
        "user_id" => $request->user_id,
        "rotation_position" => 1                              
    ]
]),  
        'name' => $request->name,
'joining_method' => $request -> joining_method,
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

$members = json_decode($group->group_members, true) ?? [];

// extract all user_ids
$userIds = array_column($members, 'user_id');

// get all users in one query (better than looping)
$users = DB::table('users')
    ->whereIn('user_id', $userIds)
    ->get(['user_id', 'ajo_score']);
        
 $currentMembers = count($members);
// sum all ajo_score values
$totalAjoScore = $users->sum('ajo_score');
$finalajoscore =  $totalAjoScore/$currentMembers;
$tier = "Bronze";

if ($finalajoscore >= 91) {
$tier = "Elite";
} elseif ($finalajoscore >= 76) {
$tier = "Gold";
} elseif ($finalajoscore >= 61) {
$tier = "Silver";
}
elseif ($finalajoscore >= 31) {
$tier = "Bronze";
}

        
         $creatorname = DB::table('users')
            ->where('user_id', $group->creator_id)
            ->first();
        
         $name = $creatorname -> full_name;

        $result[] = [
            "group_id" => $group->group_id,
            "name" => $group->name,
            "contribution_amount" => $group->contribution_amount,
            "frequency" => $group->frequency,
            "current_members" => $currentMembers,
            "max_members" => $group->max_members,
            "min_ajo_score" => $group->min_ajo_score,
            "creator_name" => $name ?? null,
            "creator_score" => $creatorname -> ajo_score ?? 0,
            "spots_remaining" => $group->max_members - $currentMembers,
            "next_contribution_date" => $group->next_contribution_date,
            "tier" =>$tier,
            "locked" => $group->lockstatus,
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

    public function myGroups(Request $request, $userId)
{
    $d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
   $groups = DB::table('groups')->get();

$result = [];

foreach ($groups as $group) {

    $members = json_decode($group->group_members, true) ?? [];

    // check if user is in group
    $myData = null;

    foreach ($members as $m) {
        if ($m['user_id'] == $userId) {
            $myData = $m;
            break;
        }
    }

    if (!$myData) {
        continue; // skip groups user is not part of
    }

    // sort members by rotation order
    usort($members, function ($a, $b) {
        return $a['rotation_position'] <=> $b['rotation_position'];
    });

    // find next recipient (user_id)
    $nextRecipient = null;
    $foundCurrent = false;

    foreach ($members as $m) {

        if ($foundCurrent) {
            $nextRecipient = $m['user_id'];
            break;
        }

        if ($m['user_id'] == $userId) {
            $foundCurrent = true;
        }
    }

    // loop back if last user
    if ($nextRecipient === null && count($members) > 0) {
        $nextRecipient = $members[0]['user_id'];
    }

    // get next recipient name from users table
    $nextUser = DB::table('users')
        ->where('user_id', $nextRecipient)
        ->first();

    $nextRecipientName = $nextUser->full_name ?? null;

    $result[] = [
        "group_id" => $group->group_id,
        "name" => $group->name,
        "contribution_amount" => $group->contribution_amount,
        "frequency" => $group->frequency,

        "my_rotation_position" => $myData['rotation_position'],

        "my_contribution_status" => "pending",

        "next_contribution_date" => $group->next_contribution_date,

        // FINAL OUTPUT (NAME instead of user_id)
        "next_recipient" => $nextRecipientName,

        "my_payout_date" => null,

        "my_payout_amount" => $group->contribution_amount * count($members),

        "current_cycle" => 1,

        "total_cycles" => 10,

        "direct_debit_active" => false
    ];
}

return response()->json([
    "success" => true,
    "data" => [
        "groups" => $result
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
    public function groupDetail( Request $request,$groupId)
{
    $d_token = $request->header('Authorization');
$accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));
if(env('REV_APP_KEY') == $accessTokennewinfo){
    $group = DB::table('groups')
        ->where('group_id', $groupId)
        ->first();

    if (!$group) {
        return response()->json([
            "success" => false,
            "message" => "Group not found"
        ], 404);
    }

    $members = json_decode($group->group_members, true) ?? [];

    $rotation = [];

    $nextIndex = 0; // simple placeholder logic

    foreach ($members as $index => $m) {

        $user = DB::table('users')
            ->where('user_id', $m['user_id'])
            ->first();

        $rotation[] = [
            "position" => $m['rotation_position'],
            "user_id" => $m['user_id'],
            "name" => $user->full_name ?? null,
            "ajo_score" => $user->ajo_score ?? 0,

            "has_received" => false,

            "is_next" => $index == $nextIndex
        ];
    }

    return response()->json([
        "success" => true,
        "data" => [
            "group_id" => $group->group_id,
            "name" => $group->name,
            "contribution_amount" => $group->contribution_amount,
            "frequency" => $group->frequency,
            "status" => $group->status ?? "active",

            "current_cycle" => 1,
            "total_cycles" => 10,

            "next_contribution_date" => $group->next_contribution_date,
            "next_disbursement_date" => $group->next_contribution_date,
            "next_disbursement_amount" => $group->contribution_amount * count($members),

            "rotation" => $rotation,

            "this_cycle_contributions" => []
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

public function joinGroup(Request $request, $groupId)
{
    $d_token = $request->header('Authorization');
    $accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));

    if (env('REV_APP_KEY') == $accessTokennewinfo) {

        $userId = $request->user_id;

        $group = DB::table('groups')
            ->where('group_id', $groupId)
            ->first();

        if (!$group) {
            return response()->json([
                "error" => [
                    "code" => "GROUP_NOT_FOUND",
                    "message" => "Group does not exist"
                ]
            ], 404);
        }

        if ($group->invite_code !== $request->invite_code) {
            return response()->json([
                "error" => [
                    "code" => "INVALID_INVITE",
                    "message" => "Invalid invite code"
                ]
            ], 400);
        }

        $members = json_decode($group->group_members, true) ?? [];

        foreach ($members as $m) {
            if ($m['user_id'] == $userId) {
                return response()->json([
                    "error" => [
                        "code" => "ALREADY_MEMBER",
                        "message" => "You are already in this group"
                    ]
                ], 400);
            }
        }

        if (count($members) >= $group->max_members) {
            return response()->json([
                "error" => [
                    "code" => "GROUP_FULL",
                    "message" => "This group has reached its maximum member count"
                ]
            ], 400);
        }

        $user = DB::table('users')
            ->where('user_id', $userId)
            ->first();

        if (!$user) {
            return response()->json([
                "error" => [
                    "code" => "USER_NOT_FOUND",
                    "message" => "User not found"
                ]
            ], 404);
        }

        if ($user->ajo_score < $group->min_ajo_score) {
            return response()->json([
                "error" => [
                    "code" => "SCORE_TOO_LOW",
                    "message" => "Your AjoScore of {$user->ajo_score} does not meet the group minimum of {$group->min_ajo_score}"
                ]
            ], 400);
        }

        $rotationPosition = count($members) + 1;

        $members[] = [
            "user_id" => $userId,
            "rotation_position" => $rotationPosition
        ];

        DB::table('groups')
            ->where('group_id', $groupId)
            ->update([
                'group_members' => json_encode($members)
            ]);

        return response()->json([
            "success" => true,
            "data" => [
                "joined" => true,
                "group_id" => $groupId,
                "rotation_position" => $rotationPosition,
                "first_contribution_date" => $group->next_contribution_date,
                "mandate_setup_required" => true,
                "mandate_setup_url" => "https://squad.co/mandate/setup/{$groupId}/{$userId}"
            ]
        ]);

    }else {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Token is invalid'
            ]
        ]);
    }
}
//stop

}
