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


    public function autoMatchGroup(Request $request)
{
    $d_token = $request->header('Authorization');

    $accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));

    // TOKEN CHECK
    if (env('REV_APP_KEY') == $accessTokennewinfo) {

        // VALIDATION
        $validator = Validator::make($request->all(), [

            'user_id' => 'required',

            'contribution_amount' => 'required|numeric|min:1',

            'frequency' => 'required|in:weekly,monthly'

        ]);

        if ($validator->fails()) {

            return response()->json([

                'success' => false,

                'message' => $validator->errors()->first()

            ]);
        }

        // SEEKER
        $seeker = DB::table('users')
            ->where('user_id', $request->user_id)
            ->first();
   $seekerdetails = DB::table('ajoscorecalculation')
                    ->where('user_id', $request->user_id)
                    ->first();
        if (!$seeker) {

            return response()->json([

                'success' => false,

                'message' => 'User not found'

            ]);
        }

        // CONTRIBUTION RANGE
        $minAmount = $request->contribution_amount * 0.8;

        $maxAmount = $request->contribution_amount * 1.2;

        // FIND GROUPS
        $groups = DB::table('groups')

            ->whereBetween('contribution_amount', [
                $minAmount,
                $maxAmount
            ])

            ->where('frequency', $request->frequency)

            // MUST BE OPEN
            ->where('status', 'awaiting_members')

            // MUST ALLOW AUTOMATCH
            ->where('joining_method', 'automatch')

            ->get();

        $recommendedGroups = [];

        foreach ($groups as $group) {

            $members = json_decode(
                $group->group_members,
                true
            ) ?? [];

          
            // USER ALREADY INSIDE?
            $alreadyMember = false;

            foreach ($members as $member) {

                if (
                    $member['user_id'] ==
                    $request->user_id
                ) {

                    $alreadyMember = true;

                    break;
                }
            }

            if ($alreadyMember) {
                continue;
            }

            // GROUP FULL?
            if (
                count($members) >=
                $group->max_members
            ) {
                continue;
            }

            $proposedMembers = [];

            $compatibilityScores = [];
 
            foreach ($members as $member) {

                $candidate = DB::table('users')
                    ->where('user_id', $members[0]['user_id'])
                    ->first();
               $candidatedetails = DB::table('ajoscorecalculation')
                    ->where('user_id', $members[0]['user_id'])
                    ->first();
                if (!$candidate) {
                    continue;
                }

                // AJO SCORE
                $scoreDiff = abs(
                    ($seeker->ajo_score ?? 0) -
                    ($candidate->ajo_score ?? 0)
                );

                $scoreProximity = max(
                    0,
                    100 - ($scoreDiff * 2)
                );

                // SAVINGS
                $savingsDiff = abs(
                    ($seekerdetails->savings_consistency ?? 0) -
                    ($candidatedetails->savings_consistency ?? 0)
                );

                $savingsCompatibility = max(
                    0,
                    100 - ($savingsDiff * 1.5)
                );

                // REPAYMENT
                $repaymentDiff = abs(
                    ($seekerdetails->repayment_behaviour ?? 0) -
                    ($candidatedetails->repayment_behaviour ?? 0)
                );

                $repaymentCompatibility = max(
                    0,
                    100 - ($repaymentDiff * 1.5)
                );



                // FINAL SCORE
                $compatibility = round(

                    ($scoreProximity * 0.40) +

                    ($savingsCompatibility * 0.30) +

                    ($repaymentCompatibility * 0.30) 

                

                );

                $compatibilityScores[] = $compatibility;

                // SCORE TIER
                $tier = "Bronze";

                if (($candidate->ajo_score ?? 0) >= 91) {

                    $tier = "Elite";

                } elseif (($candidate->ajo_score ?? 0) >= 76) {

                    $tier = "Gold";

                } elseif (($candidate->ajo_score ?? 0) >= 61) {

                    $tier = "Silver";
                }

                // DISPLAY NAME
                $nameParts = explode(
                    ' ',
                    $candidate->full_name
                );

                $displayName =
                    $nameParts[0] . ' ' .
                    strtoupper(substr(end($nameParts), 0, 1)) . '.';

                $proposedMembers[] = [

                    "member_token" =>
                        "mem_" . uniqid(),

                    "display_name" =>
                        $displayName,

                    "ajo_score" =>
                        $candidate->ajo_score ?? 0,

                    "score_tier" =>
                        $tier,

                    "contribution_consistency" =>
                        $candidate->contribution_consistency
                        ?? "New Member",

                    "compatibility_with_you" =>
                        $compatibility
                ];
            }

            // SKIP EMPTY GROUPS
            if (count($proposedMembers) == 0) {
                continue;
            }

            // GROUP SCORE
            $groupCompatibility = round(
                array_sum($compatibilityScores) /
                count($compatibilityScores)
            );

            // LABEL
            $label = "Decent Match";

            if ($groupCompatibility >= 90) {

                $label = "Best Match";

            } elseif ($groupCompatibility >= 70) {

                $label = "Good Match";
            }

            // STATS
            $avgScore = round(
                array_sum(
                    array_column(
                        $proposedMembers,
                        'ajo_score'
                    )
                ) / count($proposedMembers)
            );

            $lowestCompatibility = min(
                array_column(
                    $proposedMembers,
                    'compatibility_with_you'
                )
            );

            $membersWithHistory = count(
                array_filter(
                    $proposedMembers,
                    function ($m) {

                        return
                            $m['groups_completed'] > 0;
                    }
                )
            );

            $newMembers =
                count($proposedMembers) -
                $membersWithHistory;

            // FINAL RESPONSE ARRAY
            $recommendedGroups[] = [

                "group_id" =>
                    $group->group_id,

                "group_name" =>
                    $group->name,

                "group_creator_id" =>
                    $group->creator_id,

                "group_status" =>
                    $group->status,

                "joining_method" =>
                   $group->joining_method,

                "group_compatibility_score" =>
                    $groupCompatibility,

                "recommendation_label" =>
                    $label,

                "contribution_amount" =>
                    $group->contribution_amount,

                "frequency" =>
                    $group->frequency,

                "current_members" =>
                    count($members),

                "max_members" =>
                    $group->max_members,

                "estimated_start_date" =>
                    $group->next_contribution_date,

                "join_required" => true,

                "why_recommended" =>
                    "Members have similar financial behaviour patterns to you.",

                "proposed_members" =>
                    $proposedMembers,

                "group_stats" => [

                    "average_ajo_score" =>
                        $avgScore,

                    "members_with_group_history" =>
                        $membersWithHistory,

                    "members_new_to_groups" =>
                        $newMembers,

                    "lowest_compatibility_in_group" =>
                        $lowestCompatibility
                ]
            ];
        }

        // SORT BY BEST SCORE
        usort($recommendedGroups, function ($a, $b) {

            return
                $b['group_compatibility_score']
                <=>
                $a['group_compatibility_score'];
        });

        // RESPONSE
        return response()->json([

            "success" => true,

            "data" => [

                "seeker_profile" => [

                    "ajo_score" =>
                        $seeker->ajo_score,

                    "savings_consistency" =>
                        $seekerdetails->savings_consistency ?? 0,

                    "repayment_behaviour" =>
                        $seekerdetails->repayment_behaviour ?? 0,

                    "contribution_consistency" =>
                        $seekerdetails->contribution_consistency
                        ?? "New Member",

                    "income_range" =>
                        $seeker->income_range ?? null
                ],

                "total_matches_found" =>
                    count($recommendedGroups),

                "recommended_groups" =>
                    $recommendedGroups,

                "note" =>
                    "Review compatible groups and request to join."
            ]
        ]);

    } else {

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
