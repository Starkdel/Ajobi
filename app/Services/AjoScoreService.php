<?php

namespace App\Services;

use App\Models\User;

class AjoScoreService
{
    /**
     * Recalculate full AjoScore including bank statement score
     * Called after onboarding completion OR after bank statement upload
     */
    public function recalculate(User $user): array
    {
        // Get all component scores
        $components = [
            'savings_consistency'  => $user->savings_consistency  ?? 0,
            'repayment_behaviour'  => $user->repayment_behaviour  ?? 0,
            'escrow_completion'    => $user->escrow_completion     ?? 25,
            'transaction_history'  => $user->transaction_history  ?? 10,
            'account_maturity'     => $user->account_maturity      ?? 0,
            'community_standing'   => $user->community_standing    ?? 0,
        ];

        $bankScore = $user->bank_statement_score; // null if not uploaded yet

        // Calculate base score from onboarding components
        $baseScore =
            $components['savings_consistency']  * 0.25 +
            $components['repayment_behaviour']  * 0.25 +
            $components['escrow_completion']    * 0.20 +
            $components['transaction_history']  * 0.15 +
            $components['account_maturity']     * 0.10 +
            $components['community_standing']   * 0.05;

        // If bank statement uploaded, blend it in
        if ($bankScore !== null) {
            // Bank statement contributes 60% to final score
            // Other components contribute 30%
            $finalScore = ($baseScore * 0.30) + ($bankScore * 0.20);
        } else {
            $finalScore = ($baseScore * 0.50);
        }

        // Cap at 50, floor at 10
        $finalScore = max(min(round($finalScore), 100), 10);

        // If no bank statement, cap at 50 (onboarding only limit)
        if ($bankScore === null) {
            $finalScore = min($finalScore, 50);
        }

        $tier = $this->getTier($finalScore);

        // Save to user
        $user->update([
            'ajo_score'  => $finalScore,
            'score_tier' => $tier
        ]);

        return [
            'score'       => $finalScore,
            'tier'        => $tier,
            'components'  => $components,
            'bank_score'  => $bankScore,
            'bank_uploaded' => $bankScore !== null,
            'explanation' => $this->generateExplanation(
                                $finalScore, $components, $bankScore
                             ),
            'improvement_tips' => $this->generateTips($components, $bankScore)
        ];
    }

    private function getTier(int $score): string
    {
        if ($score >= 91) return 'Elite';
        if ($score >= 76) return 'Gold';
        if ($score >= 61) return 'Silver';
        if ($score >= 41) return 'Bronze';
        return 'Starter';
    }

    private function generateExplanation(
        int $score,
        array $components,
        ?int $bankScore
    ): string {

        $base = "Your AjoScore is {$score}. ";

        if ($bankScore !== null) {
            $base .= "Bank statement analysis contributed {$bankScore}/100 to your score. ";
        } else {
            $base .= "Upload your bank statement to unlock better scores  and improve your rating. ";
        }

        // Find weakest component
        $weakest = array_search(min($components), $components);
        $labels  = [
            'savings_consistency' => 'savings consistency',
            'repayment_behaviour' => 'repayment history',
            'escrow_completion'   => 'escrow completion',
            'transaction_history' => 'transaction activity',
            'account_maturity'    => 'account maturity',
            'community_standing'  => 'community standing'
        ];

        $base .= "Your lowest component is {$labels[$weakest]}. ";

        return $base;
    }

    private function generateTips(array $components, ?int $bankScore): array
    {
        $tips = [];

        if ($bankScore === null) {
            $tips[] = 'Upload your bank statement to unlock better scores';
        }

        if ($components['transaction_history'] <= 10) {
            $tips[] = 'Make payments through AjoBI marketplace to build transaction history';
        }

        if ($components['savings_consistency'] <= 25) {
            $tips[] = 'Join an Ajo group and contribute consistently every cycle';
        }

        if ($components['escrow_completion'] <= 25) {
            $tips[] = 'Complete your first escrow transaction to improve your escrow score';
        }

        if ($components['community_standing'] === 0) {
            $tips[] = 'Refer a friend to earn +3 points per active referral';
        }

        $tips[] = 'Every on-time Ajo contribution adds +2 points to your score';

        return array_slice($tips, 0, 4);
    }
}