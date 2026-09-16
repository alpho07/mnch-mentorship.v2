<?php

namespace App\Services\Chat;

use App\Models\User;
use Closure;
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
     * Some tools only become available once another tool has just run in
     * this same message — e.g. facility_id's options depend on county_id,
     * so if a user names both county and facility in one message, the
     * facility tool isn't offered on the first round (see
     * MentorshipSetupToolProvider::schemaFor()). Looping here, rebuilding
     * the schema from the now-updated page state after each round, lets the
     * model pick it up on a second round without the user repeating
     * themselves.
     */
    private const MAX_TOOL_ROUNDS = 4;

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  Closure(): ChatToolRegistry  $registryFactory  called fresh at the start of every round, so a tool unlocked by the previous round's execution is offered on the next one
     * @return array{reply: string, tool_calls: array<int, array{name: string, arguments: array, result: array}>}
     */
    public function respond(string $userMessage, array $history, Closure $registryFactory, User $user, array $context = []): array
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt($context)]],
            $history,
            [['role' => 'user', 'content' => $userMessage]],
        );

        $executed = [];

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $registry = $registryFactory();
            $response = $this->complete($messages, $registry->schemasFor($user));

            if ($response === null) {
                return ['reply' => self::FALLBACK_REPLY, 'tool_calls' => $executed];
            }

            $toolCalls = $response['tool_calls'] ?? [];

            if (empty($toolCalls)) {
                return ['reply' => $response['content'] ?? self::FALLBACK_REPLY, 'tool_calls' => $executed];
            }

            $messages[] = ['role' => 'assistant', 'content' => $response['content'], 'tool_calls' => $toolCalls];

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
        }

        // Round cap hit while the model still wanted to call tools — ask
        // once more for a final reply, with no tools offered, rather than
        // leaving the user with no response at all.
        $final = $this->complete($messages, []);

        return ['reply' => $final['content'] ?? self::FALLBACK_REPLY, 'tool_calls' => $executed];
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
            'program, or county names — only use the exact options a tool schema offers. '.
            "If the user's message mentions a detail (like a facility) whose options ".
            "aren't offered yet because they depend on something else you just set ".
            '(like a county), call the setup tool again once it becomes available — '.
            "don't tell the user it isn't available just because it wasn't offered on ".
            'the first attempt.';

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

        if (! empty($context['next_options'])) {
            $labels = collect($context['next_options']['options'])->pluck('label')->implode(', ');
            $prompt .= " A list of options ({$labels}) will be shown to the user directly below your reply — ".
                'write a short, warm sentence asking the question, but do NOT list the options yourself; '.
                'the app already displays them.';
        }

        return $prompt;
    }
}
