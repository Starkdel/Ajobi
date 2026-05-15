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
class fundController extends Controller
{

public function webhook(Request $request)
{
    $payload = $request->getContent();
    $signature = $request->header('x-squad-encrypted-body');

    $secret = env('SQUAD_SECRET_KEY');

    $calculatedSignature = strtoupper(hash_hmac('sha512', $payload, $secret));

    if ($signature !== $calculatedSignature) {
        return response()->json(['message' => 'Invalid signature'], 401);
    }

    $data = json_decode($payload, true);

    if (!$data) {
        return response()->json(['message' => 'Invalid payload'], 400);
    }

    $body = $data['Body'] ?? [];

    // STORE USING DB::
    DB::table('webhook_logs')->insert([
        'event' => $data['Event'] ?? null,
        'transaction_ref' => $data['TransactionRef'] ?? null,
        'transaction_type' => $body['transaction_type'] ?? null,
        'status' => $body['transaction_status'] ?? null,
        'amount' => $body['amount'] ?? null,
        'email' => $body['email'] ?? null,
        'payload' => json_encode($data), // store full JSON
        'created_at' => now(),
        'updated_at' => now(),
    ]);


  
    return response()->json(['message' => 'Webhook stored'], 200);
}




//stop
}
