<?php

namespace App\Http\Controllers;

use App\Services\SquadService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private SquadService $squad) {}

    public function callback(Request $request)
    {
        $reference = $request->query('transaction_ref');

        if (!$reference) {
            return redirect('/dashboard?payment=failed');
        }

        // Verify with Squad
        $result = $this->squad->verifyTransaction($reference);

        if (!$result['success']) {
            return redirect('/dashboard?payment=failed');
        }

        $status = $result['data']['transaction_status'] ?? null;

        if ($status === 'Success') {
            return redirect('/dashboard?payment=success');
        }

        return redirect('/dashboard?payment=failed');
    }
}