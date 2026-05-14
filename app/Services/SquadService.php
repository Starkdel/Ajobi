<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SquadService
{
    private string $secretKey;
    private string $publicKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.squad.secret_key');
        $this->publicKey = config('services.squad.public_key');
        $this->baseUrl   = config('services.squad.base_url');
    }

    // ─────────────────────────────────────────────
    // PRIVATE HELPER
    // ─────────────────────────────────────────────

    private function http()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type'  => 'application/json',
        ])->baseUrl($this->baseUrl);
    }

    private function handleResponse($response, string $action): array
    {
        if ($response->failed()) {
            Log::error("Squad API failed: {$action}", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Squad API error',
                'data'    => null,
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('data') ?? $response->json(),
        ];
    }

    // ─────────────────────────────────────────────
    // 1. PAYMENT — INITIATE TRANSACTION
    // Used for: marketplace full payment
    // ─────────────────────────────────────────────

    public function initiatePayment(array $params): array
    {
        // $params = [
        //   'email'          => 'user@email.com',
        //   'amount'         => 2500000,  // in kobo (multiply naira by 100)
        //   'currency'       => 'NGN',
        //   'callback_url'   => 'https://yourapp.com/payment/callback',
        //   'reference'      => 'unique_ref_here',
        //   'metadata'       => ['escrow_id' => 'esc_001', 'type' => 'marketplace']
        // ]

        $response = $this->http()->post('/transaction/initiate', [
            'email'        => $params['email'],
            'amount'       => $params['amount'] * 100, // convert to kobo
            'currency'     => $params['currency'] ?? 'NGN',
            'callback_url' => $params['callback_url'],
            'pass_charge'  => false,
            'transaction_ref' => $params['reference'],
            'metadata'     => $params['metadata'] ?? [],
        ]);

        return $this->handleResponse($response, 'initiatePayment');
    }

    // ─────────────────────────────────────────────
    // 2. VERIFY TRANSACTION
    // Used for: confirming payment after callback
    // ─────────────────────────────────────────────

    public function verifyTransaction(string $transactionRef): array
    {
        $response = $this->http()->get("/transaction/verify/{$transactionRef}");
        return $this->handleResponse($response, 'verifyTransaction');
    }

    // ─────────────────────────────────────────────
    // 3. TRANSFER — PAYOUT
    // Used for: Ajo disbursement, escrow release,
    //           loan disbursement, instalment release
    // ─────────────────────────────────────────────

    public function transfer(array $params): array
    {
        // $params = [
        //   'account_number'  => '0123456789',
        //   'bank_code'       => '058',
        //   'amount'          => 100000,   // in naira
        //   'currency'        => 'NGN',
        //   'reference'       => 'unique_ref_here',
        //   'narration'       => 'Ajo group payout - cycle 3',
        //   'metadata'        => ['group_id' => 'grp_001']
        // ]

        $response = $this->http()->post('/payout/transfer', [
            'account_number' => $params['account_number'],
            'bank_code'      => $params['bank_code'],
            'amount'         => $params['amount'] * 100, // convert to kobo
            'currency'       => $params['currency'] ?? 'NGN',
            'transaction_reference' => $params['reference'],
            'narration'      => $params['narration'] ?? 'AjoBI transfer',
            'meta'           => $params['metadata'] ?? [],
        ]);

        return $this->handleResponse($response, 'transfer');
    }

    // ─────────────────────────────────────────────
    // 4. DIRECT DEBIT — CREATE MANDATE
    // Used for: Ajo contributions, instalment payments,
    //           loan repayments
    // ─────────────────────────────────────────────

    public function createMandate(array $params): array
    {
        // $params = [
        //   'customer_name'    => 'Emeka Obi',
        //   'customer_email'   => 'emeka@email.com',
        //   'account_number'   => '0123456789',
        //   'bank_code'        => '058',
        //   'debit_type'       => 'fixed',  // fixed or variable
        //   'frequency'        => 'weekly', // daily, weekly, monthly
        //   'start_date'       => '2024-06-05',
        //   'end_date'         => '2024-12-05',
        //   'narration'        => 'AjoBI Ajo group contribution',
        //   'amount'           => 10000,    // in naira
        //   'reference'        => 'mnd_unique_ref'
        // ]

        $response = $this->http()->post('/mandate/create', [
            'customer_name'       => $params['customer_name'],
            'customer_email'      => $params['customer_email'],
            'account_number'      => $params['account_number'],
            'bank_code'           => $params['bank_code'],
            'debit_type'          => $params['debit_type'] ?? 'fixed',
            'frequency'           => $params['frequency'],
            'start_date'          => $params['start_date'],
            'end_date'            => $params['end_date'],
            'narration'           => $params['narration'],
            'amount'              => $params['amount'] * 100,
            'transaction_reference' => $params['reference'],
        ]);

        return $this->handleResponse($response, 'createMandate');
    }

    // ─────────────────────────────────────────────
    // 5. DIRECT DEBIT — CHARGE MANDATE
    // Used for: triggering Ajo contribution collection,
    //           collecting instalment, loan repayment
    // ─────────────────────────────────────────────

    public function chargeMandate(array $params): array
    {
        // $params = [
        //   'mandate_id'  => 'mnd_abc123',
        //   'amount'      => 10000,   // in naira
        //   'reference'   => 'unique_charge_ref'
        // ]

        $response = $this->http()->post('/mandate/charge', [
            'mandate_id'            => $params['mandate_id'],
            'amount'                => $params['amount'] * 100,
            'transaction_reference' => $params['reference'],
        ]);

        return $this->handleResponse($response, 'chargeMandate');
    }

    // ─────────────────────────────────────────────
    // 6. VIRTUAL ACCOUNT — CREATE ESCROW WALLET
    // Used for: holding escrow funds, Ajo pool
    // ─────────────────────────────────────────────

    public function createVirtualAccount(array $params): array
    {
        // $params = [
        //   'customer_identifier' => 'esc_m1n2o3',
        //   'first_name'          => 'AjoBI',
        //   'last_name'           => 'Escrow',
        //   'mobile_num'          => '08000000000',
        //   'email'               => 'escrow@ajobi.com',
        //   'bvn'                 => null,
        //   'preferred_bank'      => 'wema-bank'
        // ]

        $response = $this->http()->post('/virtual-account/create', [
            'customer_identifier' => $params['customer_identifier'],
            'first_name'          => $params['first_name'] ?? 'AjoBI',
            'last_name'           => $params['last_name']  ?? 'Escrow',
            'mobile_num'          => $params['mobile_num'] ?? '08000000000',
            'email'               => $params['email'],
            'bvn'                 => $params['bvn']        ?? null,
            'preferred_bank'      => $params['preferred_bank'] ?? 'wema-bank',
        ]);

        return $this->handleResponse($response, 'createVirtualAccount');
    }

    // ─────────────────────────────────────────────
    // 7. GET ACCOUNT BALANCE
    // Used for: checking escrow wallet balance
    //           before disbursement
    // ─────────────────────────────────────────────

    public function getAccountBalance(string $accountNumber): array
    {
        $response = $this->http()->get("/merchant/balance", [
            'account_number' => $accountNumber,
        ]);

        return $this->handleResponse($response, 'getAccountBalance');
    }

    // ─────────────────────────────────────────────
    // 8. VERIFY WEBHOOK SIGNATURE
    // Called by webhook handler to confirm
    // the request actually came from Squad
    // ─────────────────────────────────────────────

    public function verifyWebhookSignature(
        string $payload,
        string $signature
    ): bool {
        $expected = hash_hmac(
            'sha512',
            $payload,
            config('services.squad.webhook_secret')
        );

        return hash_equals($expected, strtolower($signature));
    }
}