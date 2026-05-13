<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use App\Services\AjoScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BankStatementController extends Controller
{
    public function __construct(
        private GeminiService  $gemini,
        private AjoScoreService $ajoScore
    ) {}

    public function upload(Request $request)
    {
        // Validate
        $request->validate([
            'bank_statement' => [
                'required',
                'file',
                'mimes:pdf',
                'max:5120' // 5MB max
            ]
        ]);

        $user = $request->user();

        // Check if user has already uploaded
        // Allow re-upload but note it replaces the previous one
        if ($user->bank_statement_path) {
            Storage::delete($user->bank_statement_path);
        }

        // Store the PDF
        $path = $request->file('bank_statement')->storeAs(
            'bank_statements',
            $user->id . '_' . Str::uuid() . '.pdf',
            'local' // private storage — not publicly accessible
        );

        // Get full path for Gemini
        $fullPath = Storage::disk('local')->path($path);

        // Send to Gemini for analysis
        $analysis = $this->gemini->analyzeBankStatement($fullPath);

        // Save bank statement score to user
        $user->update([
            'bank_statement_score'       => $analysis['score'],
            'bank_statement_path'        => $path,
            'bank_statement_analyzed_at' => now()
        ]);

        // Recalculate full AjoScore with bank score included
        $updatedScore = $this->ajoScore->recalculate($user);

        return response()->json([
            'success' => true,
            'data'    => [
                'bank_statement_score' => $analysis['score'],
                'bank_breakdown'       => $analysis['breakdown'],
                'bank_summary'         => $analysis['summary'],
                'red_flags'            => $analysis['red_flags'],
                'positive_signals'     => $analysis['positive_signals'],
                'analysis_valid'       => $analysis['valid'],
                'updated_ajo_score'    => $updatedScore['score'],
                'updated_tier'         => $updatedScore['tier'],
                'score_breakdown'      => $updatedScore['components'],
                'explanation'          => $updatedScore['explanation'],
                'improvement_tips'     => $updatedScore['improvement_tips'],
                'message'              => $analysis['valid']
                    ? 'Bank statement analyzed successfully. Your AjoScore has been updated.'
                    : 'We could not fully analyze your statement. A neutral score has been applied.'
            ]
        ]);
    }

    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'uploaded'             => $user->bank_statement_path !== null,
                'bank_statement_score' => $user->bank_statement_score,
                'analyzed_at'          => $user->bank_statement_analyzed_at,
                'current_ajo_score'    => $user->ajo_score,
                'score_unlocked_above_50' => $user->bank_statement_score !== null
            ]
        ]);
    }
}