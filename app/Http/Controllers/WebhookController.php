<?php

namespace App\Http\Controllers;

use App\Services\SquadService;
use App\Models\GroupContribution;
use App\Models\GroupMember;
use App\Models\AjoGroup;
use App\Models\Escrow;
use App\Models\Instalment;
use App\Models\Loan;
use App\Models\User;
use App\Services\AjoScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private SquadService    $squad,
        private AjoScoreService $ajoScore
    ) {}

    public function handle(Request $request)
    {
        // Step 1 — Verify this came from Squad
        $signature = $request->header('x-squad-encrypted-body');
        $payload   = $request->getContent();

        if (!$this->squad->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Squad webhook signature verification failed');
            return response()->json(['status' => 'unauthorized'], 401);
        }

        // Step 2 — Route to correct handler
        $event = $request->input('Event');
        $data  = $request->input('Body');

        Log::info("Squad webhook received: {$event}", $data);

        match($event) {
            'charge_successful'           => $this->handleChargeSuccessful($data),
            'transfer_successful'         => $this->handleTransferSuccessful($data),
            'transfer_failed'             => $this->handleTransferFailed($data),
            'mandate_charge_successful'   => $this->handleMandateChargeSuccessful($data),
            'mandate_charge_failed'       => $this->handleMandateChargeFailed($data),
            default => Log::info("Unhandled Squad event: {$event}")
        };

        // Always return 200 — Squad requires this
        return response()->json(['status' => 200]);
    }

    // ─────────────────────────────────────────────
    // CHARGE SUCCESSFUL
    // Triggered when: marketplace payment, escrow funding
    // ─────────────────────────────────────────────

    private function handleChargeSuccessful(array $data): void
    {
        $reference = $data['transaction_ref']  ?? null;
        $amount    = ($data['amount'] ?? 0) / 100; // convert from kobo
        $meta      = $data['meta']             ?? [];

        $type = $meta['type'] ?? null;

        match($type) {
            'escrow_funding'      => $this->fundEscrow($meta, $amount, $reference),
            'marketplace_payment' => $this->completeMarketplacePurchase($meta, $amount),
            default               => Log::info("Unhandled charge type: {$type}")
        };
    }

    private function fundEscrow(array $meta, float $amount, string $ref): void
    {
        $escrow = Escrow::find($meta['escrow_id'] ?? null);
        if (!$escrow) return;

        $escrow->update([
            'status'           => 'funded',
            'squad_reference'  => $ref,
            'funded_at'        => now(),
        ]);

        // Notify both parties via Firebase
        $this->notifyUser($escrow->creator_id, [
            'type'    => 'escrow_funded',
            'message' => "Your escrow of ₦{$amount} has been funded. Work can begin.",
        ]);

        $this->notifyUser($escrow->counterparty_id, [
            'type'    => 'escrow_funded',
            'message' => "Payment of ₦{$amount} is secured in escrow. You can start the job.",
        ]);
    }

    private function completeMarketplacePurchase(array $meta, float $amount): void
    {
        $escrow = Escrow::find($meta['escrow_id'] ?? null);
        if (!$escrow) return;

        $escrow->update(['status' => 'funded']);

        $this->notifyUser($escrow->counterparty_id, [
            'type'    => 'payment_received',
            'message' => "Payment of ₦{$amount} received and held in escrow.",
        ]);
    }

    // ─────────────────────────────────────────────
    // MANDATE CHARGE SUCCESSFUL
    // Triggered when: Ajo contribution collected,
    //                 instalment collected,
    //                 loan repayment collected
    // ─────────────────────────────────────────────

    private function handleMandateChargeSuccessful(array $data): void
    {
        $mandateId = $data['mandate_id']      ?? null;
        $amount    = ($data['amount'] ?? 0)   / 100;
        $reference = $data['transaction_ref'] ?? null;
        $meta      = $data['meta']            ?? [];

        $type = $meta['type'] ?? null;

        match($type) {
            'ajo_contribution'   => $this->recordAjoContribution($meta, $amount, $reference),
            'instalment_payment' => $this->recordInstalmentPayment($meta, $amount, $reference),
            'loan_repayment'     => $this->recordLoanRepayment($meta, $amount, $reference),
            default              => Log::info("Unhandled mandate charge type: {$type}")
        };
    }

    private function recordAjoContribution(
        array $meta,
        float $amount,
        string $reference
    ): void {
        // Mark contribution as paid
        $contribution = GroupContribution::where([
            'group_id'  => $meta['group_id'],
            'member_id' => $meta['user_id'],
            'cycle_number' => $meta['cycle_number'],
        ])->first();

        if (!$contribution) return;

        $contribution->update([
            'status'               => 'paid',
            'paid_at'              => now(),
            'squad_transaction_id' => $reference,
        ]);

        // Update AjoScore — +2 for on-time contribution
        $user = User::find($meta['user_id']);
        if ($user) {
            $user->increment('savings_consistency', 2);
            $this->ajoScore->recalculate($user);

            $this->notifyUser($user->id, [
                'type'    => 'contribution_paid',
                'message' => "Your Ajo contribution of ₦{$amount} has been collected. +2 score points.",
            ]);
        }

        // Check if all members have paid — if yes, trigger disbursement
        $this->checkAndDisburseCycle($meta['group_id'], $meta['cycle_number']);
    }

    private function recordInstalmentPayment(
        array $meta,
        float $amount,
        string $reference
    ): void {
        $instalment = Instalment::find($meta['instalment_id'] ?? null);
        if (!$instalment) return;

        $instalment->update([
            'status'               => 'paid',
            'paid_at'              => now(),
            'squad_transaction_id' => $reference,
        ]);

        $this->notifyUser($meta['buyer_id'], [
            'type'    => 'instalment_paid',
            'message' => "Instalment {$meta['instalment_number']} of ₦{$amount} collected successfully.",
        ]);

        $this->notifyUser($meta['seller_id'], [
            'type'    => 'instalment_received',
            'message' => "Instalment payment of ₦{$amount} received from buyer.",
        ]);

        // Check if all instalments paid — if yes, trigger escrow release
        $this->checkInstalmentCompletion($meta['escrow_id']);
    }

    private function recordLoanRepayment(
        array $meta,
        float $amount,
        string $reference
    ): void {
        $loan = Loan::find($meta['loan_id'] ?? null);
        if (!$loan) return;

        $loan->increment('repayments_made');
        $loan->decrement('amount_remaining', $amount);

        if ($loan->fresh()->amount_remaining <= 0) {
            $loan->update(['status' => 'repaid']);

            // Full repayment — big score boost
            $user = User::find($loan->user_id);
            if ($user) {
                $user->increment('repayment_behaviour', 10);
                $this->ajoScore->recalculate($user);

                $this->notifyUser($user->id, [
                    'type'    => 'loan_repaid',
                    'message' => 'Congratulations! Your loan has been fully repaid. +10 AjoScore points.',
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────
    // MANDATE CHARGE FAILED
    // Triggered when: contribution payment failed,
    //                 instalment failed,
    //                 loan repayment failed
    // ─────────────────────────────────────────────

    private function handleMandateChargeFailed(array $data): void
    {
        $meta   = $data['meta']   ?? [];
        $type   = $meta['type']   ?? null;
        $reason = $data['reason'] ?? 'insufficient_funds';

        match($type) {
            'ajo_contribution'   => $this->handleMissedContribution($meta, $reason),
            'instalment_payment' => $this->handleMissedInstalment($meta, $reason),
            'loan_repayment'     => $this->handleMissedRepayment($meta, $reason),
            default              => null
        };
    }

    private function handleMissedContribution(array $meta, string $reason): void
    {
        $contribution = GroupContribution::where([
            'group_id'     => $meta['group_id'],
            'member_id'    => $meta['user_id'],
            'cycle_number' => $meta['cycle_number'],
        ])->first();

        if ($contribution) {
            $contribution->update(['status' => 'missed']);
        }

        // Penalise AjoScore — -5 for missed contribution
        $user = User::find($meta['user_id']);
        if ($user) {
            $user->decrement('savings_consistency', 5);
            $this->ajoScore->recalculate($user);
        }

        // Notify the member
        $this->notifyUser($meta['user_id'], [
            'type'    => 'contribution_failed',
            'message' => "Your Ajo contribution failed ({$reason}). -5 AjoScore points. Please fund your account.",
        ]);

        // Notify group creator
        $group = AjoGroup::find($meta['group_id']);
        if ($group) {
            $this->notifyUser($group->creator_id, [
                'type'    => 'member_missed_contribution',
                'message' => "A member missed their contribution this cycle.",
            ]);
        }
    }

    private function handleMissedInstalment(array $meta, string $reason): void
    {
        $instalment = Instalment::find($meta['instalment_id'] ?? null);
        if ($instalment) {
            $instalment->update(['status' => 'missed']);
        }

        $this->notifyUser($meta['buyer_id'], [
            'type'    => 'instalment_failed',
            'message' => "Instalment payment failed ({$reason}). Please fund your account. Grace period: 48 hours.",
        ]);

        $this->notifyUser($meta['seller_id'], [
            'type'    => 'buyer_instalment_failed',
            'message' => "Buyer's instalment payment failed. They have been notified.",
        ]);
    }

    private function handleMissedRepayment(array $meta, string $reason): void
    {
        $user = User::find($meta['user_id'] ?? null);
        if (!$user) return;

        // Penalise score
        $user->decrement('repayment_behaviour', 15);
        $this->ajoScore->recalculate($user);

        $this->notifyUser($user->id, [
            'type'    => 'repayment_failed',
            'message' => "Loan repayment failed ({$reason}). -15 AjoScore points. Grace period: 7 days.",
        ]);
    }

    // ─────────────────────────────────────────────
    // TRANSFER SUCCESSFUL
    // Triggered when: Ajo disbursement sent,
    //                 escrow released,
    //                 loan disbursed
    // ─────────────────────────────────────────────

    private function handleTransferSuccessful(array $data): void
    {
        $reference = $data['transaction_ref'] ?? null;
        $amount    = ($data['amount'] ?? 0) / 100;
        $meta      = $data['meta'] ?? [];
        $type      = $meta['type'] ?? null;

        match($type) {
            'ajo_disbursement'  => $this->confirmAjoDisbursement($meta, $amount),
            'escrow_release'    => $this->confirmEscrowRelease($meta, $amount),
            'loan_disbursement' => $this->confirmLoanDisbursement($meta, $amount),
            default             => null
        };
    }

    private function confirmAjoDisbursement(array $meta, float $amount): void
    {
        $group = AjoGroup::find($meta['group_id'] ?? null);
        if (!$group) return;

        $group->increment('current_cycle');

        $this->notifyUser($meta['recipient_id'], [
            'type'    => 'ajo_disbursement_received',
            'message' => "₦{$amount} Ajo payout received successfully!",
        ]);
    }

    private function confirmEscrowRelease(array $meta, float $amount): void
    {
        $escrow = Escrow::find($meta['escrow_id'] ?? null);
        if (!$escrow) return;

        $escrow->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Update both parties' AjoScores
        $creator      = User::find($escrow->creator_id);
        $counterparty = User::find($escrow->counterparty_id);

        if ($creator) {
            $creator->increment('escrow_completion', 5);
            $this->ajoScore->recalculate($creator);
        }

        if ($counterparty) {
            $counterparty->increment('escrow_completion', 5);
            $this->ajoScore->recalculate($counterparty);
        }

        $this->notifyUser($escrow->counterparty_id, [
            'type'    => 'escrow_released',
            'message' => "₦{$amount} has been released to your account. +5 AjoScore points.",
        ]);
    }

    private function confirmLoanDisbursement(array $meta, float $amount): void
    {
        $loan = Loan::find($meta['loan_id'] ?? null);
        if (!$loan) return;

        $loan->update([
            'status'       => 'active',
            'disbursed_at' => now(),
        ]);

        $this->notifyUser($loan->user_id, [
            'type'    => 'loan_disbursed',
            'message' => "₦{$amount} loan has been sent to your account.",
        ]);
    }

    // ─────────────────────────────────────────────
    // TRANSFER FAILED
    // ─────────────────────────────────────────────

    private function handleTransferFailed(array $data): void
    {
        $meta   = $data['meta']   ?? [];
        $reason = $data['reason'] ?? 'unknown';

        Log::error('Squad transfer failed', [
            'meta'   => $meta,
            'reason' => $reason
        ]);

        $notifyUserId = $meta['recipient_id'] ?? $meta['user_id'] ?? null;

        if ($notifyUserId) {
            $this->notifyUser($notifyUserId, [
                'type'    => 'transfer_failed',
                'message' => "A transfer to your account failed. Our team has been notified.",
            ]);
        }
    }

    // ─────────────────────────────────────────────
    // HELPER — CHECK AND DISBURSE AJO CYCLE
    // Called after every successful contribution
    // to check if full cycle pot is collected
    // ─────────────────────────────────────────────

    private function checkAndDisburseCycle(string $groupId, int $cycle): void
    {
        $group = AjoGroup::with('members')->find($groupId);
        if (!$group) return;

        $totalMembers    = $group->members->count();
        $paidThisCycle   = GroupContribution::where([
            'group_id'     => $groupId,
            'cycle_number' => $cycle,
            'status'       => 'paid',
        ])->count();

        // All members paid — trigger disbursement
        if ($paidThisCycle >= $totalMembers) {
            $recipient = $group->members()
                ->where('has_received', false)
                ->orderBy('rotation_position')
                ->first();

            if (!$recipient) return;

            $squadService = app(SquadService::class);
            $user         = User::find($recipient->user_id);

            $result = $squadService->transfer([
                'account_number' => $user->bank_account_number,
                'bank_code'      => $user->bank_code,
                'amount'         => $group->contribution_amount * $totalMembers,
                'reference'      => 'DISBURSE_' . $groupId . '_' . $cycle . '_' . time(),
                'narration'      => "AjoBI group payout — {$group->name} — Cycle {$cycle}",
                'metadata'       => [
                    'type'         => 'ajo_disbursement',
                    'group_id'     => $groupId,
                    'cycle'        => $cycle,
                    'recipient_id' => $recipient->user_id,
                ],
            ]);

            if ($result['success']) {
                $recipient->update(['has_received' => true]);
            }
        }
    }

    // ─────────────────────────────────────────────
    // HELPER — CHECK INSTALMENT COMPLETION
    // Called after every instalment payment
    // ─────────────────────────────────────────────

    private function checkInstalmentCompletion(string $escrowId): void
    {
        $escrow      = Escrow::with('instalments')->find($escrowId);
        if (!$escrow) return;

        $totalInstalments = $escrow->instalments->count();
        $paidInstalments  = $escrow->instalments->where('status', 'paid')->count();

        if ($paidInstalments >= $totalInstalments) {
            // All instalments paid — auto-confirm escrow
            $escrow->update(['buyer_confirmed' => true]);

            $this->notifyUser($escrow->counterparty_id, [
                'type'    => 'all_instalments_paid',
                'message' => 'All instalments received. Please confirm goods delivery to release funds.',
            ]);
        }
    }

    // ─────────────────────────────────────────────
    // HELPER — NOTIFY USER VIA FIREBASE
    // ─────────────────────────────────────────────

    private function notifyUser(string $userId, array $notification): void
    {
        // Write to Firebase realtime DB
        // You already have Firebase configured from your existing setup
        $notificationId = 'notif_' . time() . '_' . rand(1000, 9999);

        app('firebase.database')
            ->getReference("notifications/{$userId}/{$notificationId}")
            ->set([
                'type'       => $notification['type'],
                'message'    => $notification['message'],
                'read'       => false,
                'created_at' => now()->toISOString(),
            ]);
    }
}