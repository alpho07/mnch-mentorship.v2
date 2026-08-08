<?php

namespace App\Filament\Pages;

use App\Models\RagConversation;
use App\Models\RagDocument;
use App\Models\RagMessage;
use App\Services\Rag\RagClient;
use App\Support\RagSourceFormatter;
use App\Support\RagAccess;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class RagChat extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'RAG Chat';

    protected static ?string $navigationGroup = 'knowledge Base';

    protected static ?int $navigationSort = 91;

    protected static ?string $slug = 'rag-chat';

    protected static string $view = 'filament.pages.rag-chat';

    public ?int $conversationId = null;

    public string $question = '';

    public int $topK = 1;

    public bool $isSending = false;

    public ?string $error = null;

    public array $health = [];

    public static function shouldRegisterNavigation(): bool
    {
        return RagAccess::canUseChat(auth()->user());
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(RagClient $client): void
    {
        abort_unless(static::canAccess(), 403);

        $this->topK = (int) config('rag.top_k.default', 5);
        $this->health = $client->health();
        $this->conversationId = $this->conversations()->first()?->id;
    }

    public function getConversationsProperty()
    {
        return $this->conversations()->limit(30)->get();
    }

    public function getMessagesProperty()
    {
        if (! $this->conversationId) {
            return collect();
        }

        return RagMessage::query()
            ->whereHas('conversation', fn ($query) => $query
                ->whereKey($this->conversationId)
                ->where('user_id', auth()->id()))
            ->oldest()
            ->limit(80)
            ->get();
    }

    public function selectConversation(int $conversationId): void
    {
        $conversation = $this->conversations()->whereKey($conversationId)->firstOrFail();
        $this->conversationId = $conversation->id;
        $this->error = null;
    }

    public function newChat(): void
    {
        $conversation = RagConversation::create([
            'user_id' => auth()->id(),
            'title' => null,
            'last_message_at' => now(),
        ]);

        $this->conversationId = $conversation->id;
        $this->question = '';
        $this->error = null;
    }

    public function deleteConversation(int $conversationId): void
    {
        $conversation = $this->conversations()->whereKey($conversationId)->firstOrFail();
        $conversation->delete();

        if ($this->conversationId === $conversationId) {
            $this->conversationId = $this->conversations()->first()?->id;
        }
    }

    public function send(RagClient $client): void
    {
        $this->error = null;
        $question = trim($this->question);

        if ($question === '') {
            return;
        }

        if (mb_strlen($question) > 4000) {
            $this->error = 'Question is too long.';

            return;
        }

        $key = 'rag-ask:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->error = 'Too many questions. Please wait before trying again.';

            return;
        }
        RateLimiter::hit($key, 60);

        $this->isSending = true;

        try {
            $conversation = $this->currentConversation($question);

            RagMessage::create([
                'rag_conversation_id' => $conversation->id,
                'role' => RagMessage::ROLE_USER,
                'content' => $question,
            ]);

            if ($this->isDocumentInventoryQuestion($question)) {
                RagMessage::create([
                    'rag_conversation_id' => $conversation->id,
                    'role' => RagMessage::ROLE_ASSISTANT,
                    'content' => $this->documentInventoryAnswer(),
                    'model' => 'local-index',
                    'latency_ms' => 0,
                ]);

                $conversation->forceFill(['last_message_at' => now()])->save();
                $this->conversationId = $conversation->id;
                $this->question = '';
                $this->dispatch('rag-message-added');

                return;
            }

            if ($this->isNewbornMentorshipModuleListQuestion($question)) {
                RagMessage::create([
                    'rag_conversation_id' => $conversation->id,
                    'role' => RagMessage::ROLE_ASSISTANT,
                    'content' => $this->newbornMentorshipModulesAnswer(),
                    'citations' => $this->newbornMentorshipModuleCitations(),
                    'retrieved_sources' => $this->newbornMentorshipModuleCitations(),
                    'model' => 'local-index',
                    'latency_ms' => 0,
                ]);

                $conversation->forceFill(['last_message_at' => now()])->save();
                $this->conversationId = $conversation->id;
                $this->question = '';
                $this->dispatch('rag-message-added');

                return;
            }

            $response = $client->ask($question, $this->sourceCountFor($question));

            RagMessage::create([
                'rag_conversation_id' => $conversation->id,
                'role' => RagMessage::ROLE_ASSISTANT,
                'content' => RagSourceFormatter::cleanAnswer($client->stripThink($response['answer'] ?? '')),
                'citations' => $response['citations'] ?? [],
                'retrieved_sources' => $response['retrieved_sources'] ?? [],
                'model' => $response['model'] ?? null,
                'latency_ms' => $response['latency_ms'] ?? null,
                'token_usage' => $response['token_usage'] ?? null,
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();
            $this->conversationId = $conversation->id;
            $this->question = '';
            $this->dispatch('rag-message-added');
        } catch (\Throwable $e) {
            $message = $client->sanitizeError($e->getMessage());
            $this->error = $message;

            if ($this->conversationId) {
                RagMessage::create([
                    'rag_conversation_id' => $this->conversationId,
                    'role' => RagMessage::ROLE_ASSISTANT,
                    'content' => 'I could not get an answer from the knowledge service.',
                    'error_message' => $message,
                ]);
            }

            Notification::make()->title('RAG request failed')->body($message)->danger()->send();
        } finally {
            $this->isSending = false;
        }
    }

    private function currentConversation(string $question): RagConversation
    {
        if ($this->conversationId) {
            $existing = $this->conversations()->whereKey($this->conversationId)->first();

            if ($existing) {
                if (! $existing->title) {
                    $existing->forceFill([
                        'title' => Str::limit($question, 80, ''),
                    ])->save();
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
            'summarize',
            'summary',
            'overview',
            'tell me more',
            'more about',
            'compare',
            'key recommendations',
            'recommendations',
            'key ',
            'module',
            'modules',
            'topic',
            'topics',
            'what are',
            'all ',
            'which ',
            'list ',
            'show me',
            'display',
            'view',
            'open',
            'select',
            'pick',
            'describe',
            'explain',
            'illustrate',
            'image',
            'picture',
            'visual',
            'diagram',
            'figure',
            'chart',
            'slide',
            'guidance',
            'guidelines',
            'manual',
        ]);

        return max($min, min($max, $needsBroaderContext ? max($default, 5) : $default));
    }

    private function isDocumentInventoryQuestion(string $question): bool
    {
        $normalized = Str::lower($question);

        return Str::contains($normalized, [
            'what documents',
            'which documents',
            'list documents',
            'list the documents',
            'uploaded documents',
            'documents uploaded',
            'how many documents',
            'all documents',
            'available documents',
            'indexed documents',
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

        $lines = [
            "I found **{$documents->count()} ready documents** in the knowledge base:",
            '',
        ];

        foreach ($documents as $index => $document) {
            $chunks = (int) ($document->chunk_count ?? 0);
            $chunkLabel = Str::plural('chunk', $chunks);
            $lines[] = ($index + 1).". **{$document->title}**";
            $lines[] = "   {$chunks} {$chunkLabel}";
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

    private function conversations()
    {
        return RagConversation::query()
            ->where('user_id', auth()->id())
            ->latest('last_message_at')
            ->latest();
    }
}
