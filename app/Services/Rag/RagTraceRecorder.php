<?php

namespace App\Services\Rag;

use App\Models\RagMessage;
use App\Models\RagRetrievalTrace;
use App\Services\Rag\Settings\RagSettings;
use Illuminate\Support\Arr;

class RagTraceRecorder
{
    public function __construct(
        private readonly RagSettings $settings,
        private readonly SemanticAnswerCache $cache,
        private readonly Answerability $answerability,
        private readonly GroundednessVerifier $grounding,
    ) {}

    public function record(RagMessage $message, string $question, array $response, ?int $userId = null): ?RagRetrievalTrace
    {
        if (! (bool) config('rag.trace.enabled', true)) {
            return null;
        }

        $sources = $response['retrieved_sources'] ?? $response['citations'] ?? [];
        $gate = $this->answerability->assess($question, is_array($sources) ? $sources : []);
        $grounding = $this->grounding->report((string) ($response['answer'] ?? $message->content ?? ''), is_array($sources) ? $sources : []);
        $metadata = is_array($response['token_usage'] ?? null) ? $response['token_usage'] : [];
        $trace = Arr::get($metadata, 'retrieval_trace', []);

        return RagRetrievalTrace::query()->create([
            'rag_message_id' => $message->id,
            'rag_conversation_id' => $message->rag_conversation_id,
            'user_id' => $userId,
            'question' => $question,
            'question_hash' => $this->cache->hash($question),
            'decision' => $this->effectiveDecision($gate['decision']),
            'gate_score' => $gate['score'],
            'gate_signals' => $gate['signals'],
            'gate_mode' => (string) $this->settings->get('gate.mode', 'shadow'),
            'shadow_decision' => $gate['decision'],
            'stages' => $trace['stages'] ?? null,
            'final_stage' => $trace['profile'] ?? null,
            'search_count' => (int) ($trace['search_count'] ?? 0),
            'primary_queries' => $trace['primary_queries'] ?? null,
            'expanded_queries' => $trace['fallback_queries'] ?? null,
            'lexicon_edges_used' => $trace['lexicon_edges_used'] ?? null,
            'source_count' => count($sources),
            'selected_documents' => $trace['selected_documents'] ?? collect($sources)->pluck('document')->filter()->unique()->values()->all(),
            'selected_locators' => $trace['selected_locations'] ?? null,
            'answer_route' => $this->routeFromModel((string) ($response['model'] ?? '')),
            'answer_model' => $response['model'] ?? null,
            'cache_hit' => (bool) ($response['cache_hit'] ?? false),
            'cache_kind' => $response['cache_kind'] ?? null,
            'cache_similarity' => $response['cache_similarity'] ?? null,
            'grounding_min_support' => $grounding['grounding_min_support'],
            'sentence_count' => $grounding['sentence_count'],
            'unsupported_count' => $grounding['unsupported_count'],
            'numeric_violation_count' => $grounding['numeric_violation_count'],
            'unsupported_sentences' => $grounding['unsupported_sentences'],
            'retrieval_ms' => (int) ($trace['retrieval_ms'] ?? 0),
            'answer_ms' => max(0, (int) ($response['latency_ms'] ?? 0) - (int) ($trace['retrieval_ms'] ?? 0)),
            'total_ms' => (int) ($response['latency_ms'] ?? 0),
            'budget_ms' => (int) $this->settings->get('budget.total_ms', 12000),
            'budget_exhausted' => (bool) ($trace['budget_exhausted'] ?? false),
            'corpus_version' => $this->settings->corpusVersion(),
            'settings_version' => $this->settings->version(),
            'fallback_reason' => $response['fallback_reason'] ?? null,
            'error_message' => $message->error_message,
        ]);
    }

    private function effectiveDecision(string $shadowDecision): string
    {
        return $this->settings->get('gate.mode', 'shadow') === 'enforce' ? $shadowDecision : 'answer';
    }

    private function routeFromModel(string $model): string
    {
        return match (true) {
            $model === 'local-corpus-listing' => 'listing',
            str_contains($model, 'cache') => 'cache',
            str_starts_with($model, 'local') => 'local',
            default => 'remote',
        };
    }
}
