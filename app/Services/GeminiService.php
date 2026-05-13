<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('services.gemini.api_key');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
    }

    public function analyzeBankStatement(string $pdfPath): array
    {
        try {
            // Read PDF and convert to base64
            $pdfContent = file_get_contents($pdfPath);
            $base64Pdf  = base64_encode($pdfContent);

            $prompt = $this->buildScoringPrompt();

            $response = Http::timeout(60)->post(
                $this->baseUrl . '?key=' . $this->apiKey,
                [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'inline_data' => [
                                        'mime_type' => 'application/pdf',
                                        'data'      => $base64Pdf
                                    ]
                                ],
                                [
                                    'text' => $prompt
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.1,
                        'responseMediaType' => 'application/json'
                    ]
                ]
            );

            if ($response->failed()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body'   => $response->body()
                ]);
                return $this->fallbackScore();
            }

            return $this->parseGeminiResponse($response->json());

        } catch (\Exception $e) {
            Log::error('GeminiService error: ' . $e->getMessage());
            return $this->fallbackScore();
        }
    }

    private function buildScoringPrompt(): string
    {
        return <<<PROMPT
You are a financial analyst scoring a Nigerian bank statement for creditworthiness.
Analyze this bank statement and return ONLY a valid JSON object with no extra text.

Score each component from 0 to 100 based on what you see in the statement:

1. income_regularity (0-100)
   - How regular and consistent is income coming in?
   - Regular monthly salary = high score
   - Irregular informal income = medium score
   - No clear income pattern = low score

2. savings_behaviour (0-100)
   - Does the account holder save money?
   - Regular transfers to savings = high score
   - Occasional savings = medium score
   - Spends everything, no savings = low score

3. spending_discipline (0-100)
   - Are expenses controlled relative to income?
   - Expenses well below income = high score
   - Expenses close to income = medium score
   - Expenses exceed income regularly = low score

4. account_activity (0-100)
   - How active is the account?
   - Regular frequent transactions = high score
   - Occasional transactions = medium score
   - Very few transactions = low score

5. debt_obligations (0-100)
   - Are there loan repayments? Are they consistent?
   - No debt or debt repaid on time = high score
   - Some missed repayments = medium score
   - Many missed repayments = low score

6. balance_trend (0-100)
   - Is the account balance growing, stable, or declining?
   - Growing trend = high score
   - Stable = medium score
   - Declining trend = low score

Return ONLY this JSON structure, nothing else:
{
  "income_regularity": <number 0-100>,
  "savings_behaviour": <number 0-100>,
  "spending_discipline": <number 0-100>,
  "account_activity": <number 0-100>,
  "debt_obligations": <number 0-100>,
  "balance_trend": <number 0-100>,
  "overall_score": <weighted average 0-100>,
  "summary": "<one sentence summary of the financial health>",
  "red_flags": ["<flag1>", "<flag2>"],
  "positive_signals": ["<signal1>", "<signal2>"]
}

If you cannot read the document or it is not a bank statement, return:
{
  "error": "invalid_document",
  "overall_score": 0
}
PROMPT;
    }

    private function parseGeminiResponse(array $response): array
    {
        try {
            // Extract text from Gemini response structure
            $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Clean up response — remove markdown code blocks if present
            $text = preg_replace('/```json\s*/', '', $text);
            $text = preg_replace('/```\s*/', '', $text);
            $text = trim($text);

            $data = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Gemini JSON response', ['text' => $text]);
                return $this->fallbackScore();
            }

            // If Gemini flagged invalid document
            if (isset($data['error'])) {
                return [
                    'score'            => 0,
                    'breakdown'        => [],
                    'summary'          => 'Could not read document',
                    'red_flags'        => ['Document could not be analyzed'],
                    'positive_signals' => [],
                    'valid'            => false
                ];
            }

            // Calculate final score (weighted)
            $score = $this->calculateFinalScore($data);

            return [
                'score'            => $score,
                'breakdown'        => [
                    'income_regularity'   => $data['income_regularity']   ?? 0,
                    'savings_behaviour'   => $data['savings_behaviour']   ?? 0,
                    'spending_discipline' => $data['spending_discipline'] ?? 0,
                    'account_activity'    => $data['account_activity']    ?? 0,
                    'debt_obligations'    => $data['debt_obligations']    ?? 0,
                    'balance_trend'       => $data['balance_trend']       ?? 0,
                ],
                'summary'          => $data['summary']          ?? '',
                'red_flags'        => $data['red_flags']        ?? [],
                'positive_signals' => $data['positive_signals'] ?? [],
                'valid'            => true
            ];

        } catch (\Exception $e) {
            Log::error('parseGeminiResponse error: ' . $e->getMessage());
            return $this->fallbackScore();
        }
    }

    private function calculateFinalScore(array $data): int
    {
        // Weighted scoring — income and savings matter most
        $weighted =
            ($data['income_regularity']   ?? 0) * 0.25 +
            ($data['savings_behaviour']   ?? 0) * 0.25 +
            ($data['spending_discipline'] ?? 0) * 0.20 +
            ($data['account_activity']    ?? 0) * 0.10 +
            ($data['debt_obligations']    ?? 0) * 0.10 +
            ($data['balance_trend']       ?? 0) * 0.10;

        // Cap at 100
        return (int) min(round($weighted), 100);
    }

    private function fallbackScore(): array
    {
        // If Gemini fails for any reason
        // Return neutral score so user is not penalised
        return [
            'score'            => 50,
            'breakdown'        => [],
            'summary'          => 'Analysis unavailable. Neutral score applied.',
            'red_flags'        => [],
            'positive_signals' => [],
            'valid'            => false
        ];
    }
}