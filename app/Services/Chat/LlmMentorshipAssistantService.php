<?php

namespace App\Services\Chat;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wraps DeepSeek-V3's OpenAI-compatible chat completions endpoint and runs
 * the standard two-step tool-calling loop: send message + tool schema,
 * execute any tool calls server-side, send results back for a final
 * natural-language reply. See
 * docs/superpowers/specs/2026-08-03-mnchgpt-llm-assistant-design.md.
 */
class LlmMentorshipAssistantService
{
    private const FALLBACK_REPLY = "Sorry, I couldn't process that — try again or use the buttons below.";

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, tool_calls: array<int, array{name: string, arguments: array, result: array}>}
     */
    public function respond(string $userMessage, array $history, ChatToolRegistry $registry, User $user, array $context = []): array
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt($context)]],
            $history,
            [['role' => 'user', 'content' => $userMessage]],
        );

        $tools = $registry->schemasFor($user);

        $first = $this->complete($messages, $tools);

        if ($first === null) {
            return ['reply' => self::FALLBACK_REPLY, 'tool_calls' => []];
        }

        $toolCalls = $first['tool_calls'] ?? [];

        if (empty($toolCalls)) {
            return ['reply' => $first['content'] ?? self::FALLBACK_REPLY, 'tool_calls' => []];
        }

        $executed = [];
        $messages[] = ['role' => 'assistant', 'content' => $first['content'], 'tool_calls' => $toolCalls];

        foreach ($toolCalls as $call) {
            $name = $call['function']['name'];
            $args = json_decode($call['function']['arguments'] ?? '{}', true) ?? [];

            try {
                $result = $registry->execute($name, $args, $user);
            } catch (\Throwable $e) {
                Log::warning('Chat tool execution failed', ['tool' => $name, 'error' => $e->getMessage()]);
                $result = ['error' => 'That could not be completed.'];
            }

            $executed[] = ['name' => $name, 'arguments' => $args, 'result' => $result];

            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $call['id'],
                'content' => json_encode($result),
            ];
        }

        $second = $this->complete($messages, $tools);

        return [
            'reply' => $second['content'] ?? self::FALLBACK_REPLY,
            'tool_calls' => $executed,
        ];
    }

    /**
     * @return array{content: ?string, tool_calls: array}|null null on any
     *                                                         request failure — callers treat that as the fallback path.
     */
    private function complete(array $messages, array $tools): ?array
    {
        try {
            $response = Http::withToken(config('services.deepseek.api_key'))
                ->timeout(20)
                ->post(rtrim(config('services.deepseek.base_url'), '/').'/chat/completions', array_filter([
                    'model' => config('services.deepseek.model'),
                    'messages' => $messages,
                    'tools' => $tools ?: null,
                ]));

            if (! $response->successful()) {
                Log::warning('DeepSeek request failed', ['status' => $response->status()]);

                return null;
            }

            $message = $response->json('choices.0.message');

            if ($message === null) {
                return null;
            }

            return [
                'content' => $message['content'] ?? null,
                'tool_calls' => $message['tool_calls'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('DeepSeek request threw', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function systemPrompt(array $context): string
    {
        $prompt = 'You are MNCHGPT, an assistant that helps set up mentorship '.
            'programs and answers questions about mentorship and assessment data. '.
            'Use the available tools to fill in mentorship details from what the '.
            'user tells you, or to look up data they ask about. Never invent facility, '.
            'program, or county names — only use the exact options a tool schema offers.';

        if (! empty($context['remaining_requirements'])) {
            $outstanding = collect($context['remaining_requirements'])
                ->where('filled', false)
                ->pluck('label')
                ->implode('; ');

            if ($outstanding !== '') {
                $prompt .= " Still outstanding for this mentorship: {$outstanding}. ".
                    'Always mention everything still outstanding in your reply, not just the next single item.';
            }
        }

        return $prompt;
    }
}
