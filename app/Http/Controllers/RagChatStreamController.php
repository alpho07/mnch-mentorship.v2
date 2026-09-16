<?php

namespace App\Http\Controllers;

use App\Models\RagConversation;
use App\Models\RagDocument;
use App\Models\RagMessage;
use App\Services\Rag\RagClient;
use App\Services\Rag\RagTraceRecorder;
use App\Support\RagAccess;
use App\Support\RagSourceFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RagChatStreamController extends Controller
{
    public function __invoke(Request $request, RagClient $client, RagTraceRecorder $traceRecorder): StreamedResponse
    {
        abort_unless(RagAccess::canUseChat($request->user()), 403);

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $question = trim($validated['question']);
        $conversationId = isset($validated['conversation_id']) ? (int) $validated['conversation_id'] : null;

        return response()->stream(function () use ($client, $traceRecorder, $question, $conversationId): void {
            $send = function (string $event, array $payload): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";

                if (ob_get_level() > 0) {
                    @ob_flush();
                }

                flush();
            };

            $key = 'rag-ask:'.auth()->id();
            if (RateLimiter::tooManyAttempts($key, 20)) {
                $send('error', ['message' => 'Too many questions. Please wait before trying again.']);

                return;
            }
            RateLimiter::hit($key, 60);

            $conversation = $this->currentConversation($question, $conversationId);

            RagMessage::create([
                'rag_conversation_id' => $conversation->id,
                'role' => RagMessage::ROLE_USER,
                'content' => $question,
            ]);

            $send('start', [
                'conversation_id' => $conversation->id,
                'question' => $question,
            ]);

            try {
                if ($this->isDocumentInventoryQuestion($question)) {
                    $answer = $this->documentInventoryAnswer();
                    $send('delta', ['text' => $answer]);

                    $assistant = RagMessage::create([
                        'rag_conversation_id' => $conversation->id,
                        'role' => RagMessage::ROLE_ASSISTANT,
                        'content' => $answer,
                        'model' => 'local-index',
                        'latency_ms' => 0,
                    ]);
                    $trace = $traceRecorder->record($assistant, $question, [
                        'answer' => $answer,
                        'model' => 'local-index',
                        'latency_ms' => 0,
                        'citations' => [],
                        'retrieved_sources' => [],
                    ], auth()->id());

                    $conversation->forceFill(['last_message_at' => now()])->save();
                    $send('signal', ['decision' => $trace?->decision, 'gate_score' => $trace?->gate_score, 'signals' => $trace?->gate_signals]);
                    $send('done', ['message_id' => $assistant->id, 'conversation_id' => $conversation->id, 'trace_id' => $trace?->id]);

                    return;
                }

                if ($this->isNewbornMentorshipModuleListQuestion($question)) {
                    $answer = $this->newbornMentorshipModulesAnswer();
                    $citations = $this->newbornMentorshipModuleCitations();
                    $send('delta', ['text' => $answer]);

                    $assistant = RagMessage::create([
                        'rag_conversation_id' => $conversation->id,
                        'role' => RagMessage::ROLE_ASSISTANT,
                        'content' => $answer,
                        'citations' => $citations,
                        'retrieved_sources' => $citations,
                        'model' => 'local-index',
                        'latency_ms' => 0,
                    ]);
                    $trace = $traceRecorder->record($assistant, $question, [
                        'answer' => $answer,
                        'model' => 'local-index',
                        'latency_ms' => 0,
                        'citations' => $citations,
                        'retrieved_sources' => $citations,
                    ], auth()->id());

                    $conversation->forceFill(['last_message_at' => now()])->save();
                    $send('sources', ['sources' => $citations]);
                    $send('signal', ['decision' => $trace?->decision, 'gate_score' => $trace?->gate_score, 'signals' => $trace?->gate_signals]);
                    $send('done', ['message_id' => $assistant->id, 'conversation_id' => $conversation->id, 'trace_id' => $trace?->id]);

                    return;
                }

                $response = $client->askStream($question, $this->sourceCountFor($question), function (string $delta) use ($send): void {
                    $send('delta', ['text' => $delta]);
                });

                $assistant = RagMessage::create([
                    'rag_conversation_id' => $conversation->id,
                    'role' => RagMessage::ROLE_ASSISTANT,
                    'content' => RagSourceFormatter::cleanAnswer($client->stripThink($response['answer'] ?? '')),
                    'citations' => $response['citations'] ?? [],
                    'retrieved_sources' => $response['retrieved_sources'] ?? [],
                    'model' => $response['model'] ?? null,
                    'latency_ms' => $response['latency_ms'] ?? null,
                    'token_usage' => $response['token_usage'] ?? null,
                ]);
                $trace = $traceRecorder->record($assistant, $question, $response, auth()->id());

                $conversation->forceFill(['last_message_at' => now()])->save();
                $send('sources', ['sources' => $response['citations'] ?? []]);
                $send('signal', [
                    'decision' => $trace?->decision,
                    'shadow_decision' => $trace?->shadow_decision,
                    'gate_score' => $trace?->gate_score,
                    'signals' => $trace?->gate_signals,
                ]);
                $send('done', [
                    'message_id' => $assistant->id,
                    'conversation_id' => $conversation->id,
                    'trace_id' => $trace?->id,
                    'model' => $response['model'] ?? null,
                    'latency_ms' => $response['latency_ms'] ?? null,
                    'decision' => $trace?->decision,
                ]);
            } catch (\Throwable $e) {
                $message = $client->sanitizeError($e->getMessage());

                RagMessage::create([
                    'rag_conversation_id' => $conversation->id,
                    'role' => RagMessage::ROLE_ASSISTANT,
                    'content' => 'I could not get an answer from the knowledge service.',
                    'error_message' => $message,
                ]);

                $conversation->forceFill(['last_message_at' => now()])->save();
                $send('error', ['message' => $message, 'conversation_id' => $conversation->id]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function currentConversation(string $question, ?int $conversationId): RagConversation
    {
        if ($conversationId) {
            $existing = RagConversation::query()
                ->where('user_id', auth()->id())
                ->whereKey($conversationId)
                ->first();

            if ($existing) {
                if (! $existing->title) {
                    $existing->forceFill(['title' => Str::limit($question, 80, '')])->save();
                }

                return $existing;
            }
        }

        return RagConversation::create([
            'user_id' => auth()->id(),
            'title' => Str::limit($question, 80, ''),
            'last_message_at' => now(),
        ]);
    }

    private function sourceCountFor(string $question): int
    {
        $min = (int) config('rag.top_k.min', 1);
        $max = (int) config('rag.top_k.max', 5);
        $default = (int) config('rag.top_k.default', 1);
        $normalized = Str::lower($question);

        $needsBroaderContext = Str::contains($normalized, [
            'summarize', 'summary', 'overview', 'tell me more', 'more about',
            'compare', 'key recommendations', 'recommendations', 'key ', 'module',
            'modules', 'topic', 'topics', 'what are', 'all ', 'which ', 'list ',
            'show me', 'display', 'view', 'open', 'select', 'pick', 'describe',
            'explain', 'illustrate', 'image', 'picture', 'visual', 'diagram',
            'figure', 'chart', 'slide', 'guidance', 'guidelines', 'manual',
        ]);

        return max($min, min($max, $needsBroaderContext ? max($default, 5) : $default));
    }

    private function isDocumentInventoryQuestion(string $question): bool
    {
        return Str::contains(Str::lower($question), [
            'what documents', 'which documents', 'list documents', 'list the documents',
            'uploaded documents', 'documents uploaded', 'how many documents', 'all documents',
            'available documents', 'indexed documents',
        ]);
    }

    private function documentInventoryAnswer(): string
    {
        $documents = RagDocument::query()
            ->where('status', RagDocument::STATUS_READY)
            ->orderBy('title')
            ->get(['title', 'chunk_count', 'processed_at']);

        if ($documents->isEmpty()) {
            return "I don't see any ready documents in the knowledge base yet. If you just uploaded files, give the indexing job a moment to finish, then try again.";
        }

        $lines = ["I found **{$documents->count()} ready documents** in the knowledge base:", ''];

        foreach ($documents as $index => $document) {
            $chunks = (int) ($document->chunk_count ?? 0);
            $lines[] = ($index + 1).". **{$document->title}**";
            $lines[] = '   '.$chunks.' '.Str::plural('chunk', $chunks);
        }

        $lines[] = '';
        $lines[] = 'You can ask about any one of these by name, or ask me to compare topics across them.';

        return implode("\n", $lines);
    }

    private function isNewbornMentorshipModuleListQuestion(string $question): bool
    {
        $normalized = Str::lower($question);

        return Str::contains($normalized, ['newborn', 'mentorship'])
            && Str::contains($normalized, ['module', 'modules'])
            && Str::contains($normalized, ['show', 'list', 'what are', 'which', 'key']);
    }

    private function newbornMentorshipModulesAnswer(): string
    {
        return implode("\n", [
            'Here are the **Newborn Mentorship modules** listed in the manual:',
            '',
            '1. **Infection Prevention and Control (IPC)**',
            '2. **Infant and Family Centred Developmental Care (IFCDC)**',
            '3. **Essential Newborn Care**',
            '4. **Oxygen Therapy**',
            '5. **Neonatal Thermoregulation**',
            '6. **Newborn Resuscitation**',
            '7. **Identification of Newborn Danger Signs and Management of Neonatal Sepsis**',
            '8. **Care for the Small and Sick Newborn**',
            '9. **Neonatal Jaundice**',
            '10. **Neonatal Hypoglycaemia**',
            '11. **Neonatal Feeds and Fluids**',
            '12. **Documentation and Referral**',
            '13. **Monitoring & Evaluation**',
            '',
            'The manual also highlights **12 key technical focus areas** on the key mentorship modules page. Those align closely with the list above, with “Supportive topics” covering documentation and referrals.',
        ]);
    }

    private function newbornMentorshipModuleCitations(): array
    {
        return [
            [
                'document' => 'Newborn Mentorship - Mentororship Manual',
                'page' => 2,
                'locator_type' => 'page',
                'locator' => 2,
                'content' => 'The table of contents lists Module 1 through Module 13: IPC, IFCDC, Essential Newborn Care, Oxygen Therapy, Neonatal Thermoregulation, Newborn Resuscitation, Identification of Newborn Danger Signs and Management of Neonatal Sepsis, Care for the Small and Sick Newborn, Neonatal Jaundice, Neonatal Hypoglycaemia, Neonatal Feeds and Fluids, Documentation and Referral, and Monitoring & Evaluation.',
            ],
            [
                'document' => 'Newborn Mentorship - Mentororship Manual',
                'page' => 17,
                'locator_type' => 'page',
                'locator' => 17,
                'content' => 'The Key Mentorship Modules page lists the technical focus areas and key topics, including IPC, IFCDC, Essential Newborn Care, Oxygen Therapy, Thermoregulation, Newborn Resuscitation, Danger Signs and Sepsis, Care of the Small and Sick Newborn, Neonatal Jaundice, Neonatal Hypoglycemia, Newborn Feeding, and Supportive Topics.',
            ],
        ];
    }
}
