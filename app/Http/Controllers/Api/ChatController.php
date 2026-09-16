<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    public function assistant(Request $request)
    {
        $request->validate([
            'messages' => 'required|array|min:1',
            'messages.*.role' => 'required|string|in:user,assistant',
            'messages.*.content' => 'required|string',
            'system' => 'nullable|string',
        ]);

        $apiKey = config('services.anthropic.api_key');

        if (! $apiKey) {
            return response()->json([
                'reply' => 'The AI assistant is not configured yet. Please ask the administrator to set the ANTHROPIC_API_KEY.',
            ], 200);
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 1000,
                'system' => $request->input('system', ''),
                'messages' => $request->input('messages'),
            ]);

            if ($response->failed()) {
                return response()->json([
                    'reply' => 'Sorry, the AI assistant is temporarily unavailable. Please try again later.',
                ], 200);
            }

            $content = $response->json('content', []);
            $text = collect($content)
                ->pluck('text')
                ->filter()
                ->implode('');

            return response()->json([
                'reply' => $text ?: 'Sorry, I couldn\'t generate a response.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'reply' => 'Sorry, I\'m having trouble connecting. Please try again later.',
            ], 200);
        }
    }
}
