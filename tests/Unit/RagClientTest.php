<?php

namespace Tests\Unit;

use App\Services\Rag\DocumentTextExtractor;
use App\Services\Rag\RagClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RagClientTest extends TestCase
{
    public function test_ask_normalizes_citations_and_strips_think_blocks(): void
    {
        config()->set('rag.engine', 'local');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');

        Http::fake([
            '127.0.0.1:8001/ask' => Http::response([
                'answer' => '<think>hidden</think>Use magnesium sulfate.',
                'sources' => [
                    ['document' => 'EmONC', 'page' => 17, 'content' => '<b>source</b>'],
                ],
                'model' => 'local',
            ]),
        ]);

        $response = app(RagClient::class)->ask('What is used for eclampsia?', 50);

        $this->assertSame('Use magnesium sulfate.', $response['answer']);
        $this->assertSame(17, $response['citations'][0]['page']);
        $this->assertSame('source', $response['citations'][0]['content']);
        $this->assertSame('local', $response['model']);
    }

    public function test_ask_rejects_malformed_json(): void
    {
        config()->set('rag.engine', 'local');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');

        Http::fake([
            '127.0.0.1:8001/ask' => Http::response('not-json', 200),
        ]);

        $this->expectException(\RuntimeException::class);

        app(RagClient::class)->ask('Question?', 5);
    }

    public function test_hybrid_ask_uses_local_search_and_external_chat(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');
        config()->set('rag.chat.provider', 'deepseek');
        config()->set('rag.chat.base_url', 'https://api.deepseek.com');
        config()->set('rag.chat.api_key', 'test-key');
        config()->set('rag.chat.model', 'deepseek-chat');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '127.0.0.1:8001/search')) {
                $query = $request->data()['question'] ?? '';

                return Http::response([
                    'sources' => [
                        [
                            'document' => $query === 'prioritization' ? 'Module 2' : 'Module 1',
                            'locator_type' => 'slide',
                            'locator' => $query === 'prioritization' ? 4 : 3,
                            'content' => $query === 'prioritization'
                                ? 'Prioritization identifies who needs urgent care first.'
                                : 'Triage is sorting and prioritizing patients.',
                        ],
                    ],
                    'timings' => ['retrieval_ms' => 10],
                ]);
            }

            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'Triage sorts and prioritizes patients [1].']],
                ],
                'model' => 'deepseek-chat',
            ]);
        });

        $response = app(RagClient::class)->ask('What is triage prioritization?', 5);

        $this->assertSame('Triage sorts and prioritizes patients [1].', $response['answer']);
        $this->assertSame('deepseek-chat', $response['model']);
        $this->assertContains('Module 1', collect($response['citations'])->pluck('document')->all());
        $this->assertContains('Module 2', collect($response['citations'])->pluck('document')->all());
        $this->assertSame('standard', $response['token_usage']['retrieval_trace']['profile']);
        $this->assertGreaterThanOrEqual(1, $response['token_usage']['retrieval_trace']['search_count']);
        $this->assertContains('Module 1', $response['token_usage']['retrieval_trace']['selected_documents']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '127.0.0.1:8001/search')
                && ($request->data()['question'] ?? '') === 'prioritization';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '127.0.0.1:8001/search')
                && ($request->data()['question'] ?? '') === 'triage prioritization management';
        });
    }

    public function test_hybrid_visual_request_returns_media_without_external_chat(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');

        Http::fake([
            '127.0.0.1:8001/search' => Http::response([
                'sources' => [
                    [
                        'document_id' => 'daedc522-7aa7-46f6-b0d3-8b66e89e4d18',
                        'document' => 'Module 3. Essential Newborn Care',
                        'locator_type' => 'slide',
                        'locator' => 8,
                        'content' => 'Assessment of The Newborn',
                        'media' => [
                            ['filename' => 'slide-8-image-4.png', 'content_type' => 'image/png'],
                        ],
                    ],
                ],
                'timings' => ['retrieval_ms' => 10],
            ]),
        ]);

        $response = app(RagClient::class)->ask('show me assessment of newborn', 8);

        $this->assertSame('local-media', $response['model']);
        $this->assertStringContainsString('Module 3. Essential Newborn Care', $response['answer']);
        $this->assertCount(1, $response['citations'][0]['media']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '127.0.0.1:8001/search')
                && ($request->data()['question'] ?? '') === 'show me assessment of newborn';
        });
    }

    public function test_hybrid_select_request_returns_media_without_external_chat(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');

        Http::fake([
            '127.0.0.1:8001/search' => Http::response([
                'sources' => [
                    [
                        'document_id' => 'daedc522-7aa7-46f6-b0d3-8b66e89e4d18',
                        'document' => 'Module 3. Essential Newborn Care',
                        'locator_type' => 'slide',
                        'locator' => 8,
                        'content' => 'Assessment of The Newborn',
                        'media' => [
                            ['filename' => 'slide-8-image-4.png', 'content_type' => 'image/png'],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = app(RagClient::class)->ask('select assessment of newborn', 8);

        $this->assertSame('local-media', $response['model']);
        $this->assertCount(1, $response['citations'][0]['media']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '127.0.0.1:8001/search')
                && ($request->data()['question'] ?? '') === 'select assessment of newborn';
        });
    }

    public function test_hybrid_describe_request_prioritizes_media_and_uses_external_chat(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');
        config()->set('rag.chat.provider', 'deepseek');
        config()->set('rag.chat.base_url', 'https://api.deepseek.com');
        config()->set('rag.chat.api_key', 'test-key');
        config()->set('rag.chat.model', 'deepseek-chat');

        Http::fake([
            '127.0.0.1:8001/search' => Http::response([
                'sources' => [
                    [
                        'document' => 'Text-only page',
                        'locator_type' => 'page',
                        'locator' => 2,
                        'content' => 'Assessment notes.',
                    ],
                    [
                        'document_id' => 'daedc522-7aa7-46f6-b0d3-8b66e89e4d18',
                        'document' => 'Module 3. Essential Newborn Care',
                        'locator_type' => 'slide',
                        'locator' => 8,
                        'content' => 'Image text: Assessment of The Newborn',
                        'media' => [
                            ['filename' => 'slide-8-image-4.png', 'content_type' => 'image/png'],
                        ],
                    ],
                ],
            ]),
            'api.deepseek.com/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'The visual summarizes newborn assessment steps [1].']],
                ],
                'model' => 'deepseek-chat',
            ]),
        ]);

        $response = app(RagClient::class)->ask('describe assessment of newborn', 8);

        $this->assertSame('deepseek-chat', $response['model']);
        $mediaSource = collect($response['citations'])->first(fn (array $source): bool => ($source['document'] ?? null) === 'Module 3. Essential Newborn Care'
            && ! empty($source['media'] ?? []));
        $this->assertNotNull($mediaSource);
        $this->assertCount(1, $mediaSource['media'] ?? []);
        $this->assertStringContainsString('newborn assessment', $response['answer']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '127.0.0.1:8001/search')
                && ($request->data()['question'] ?? '') === 'describe assessment of newborn';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.deepseek.com/chat/completions');
        });
    }

    public function test_hybrid_empty_chat_response_falls_back_to_retrieved_sources(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');
        config()->set('rag.chat.provider', 'deepseek');
        config()->set('rag.chat.base_url', 'https://api.deepseek.com');
        config()->set('rag.chat.api_key', 'test-key');
        config()->set('rag.chat.model', 'deepseek-chat');

        Http::fake([
            '127.0.0.1:8001/search' => Http::response([
                'sources' => [
                    [
                        'document' => 'ZxqvAlpha Source',
                        'locator_type' => 'page',
                        'locator' => 12,
                        'content' => 'Key messages include preparing the team, practicing emergency roles, and reviewing drill performance.',
                    ],
                ],
            ]),
            'api.deepseek.com/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => '']],
                ],
                'model' => 'deepseek-chat',
            ]),
        ]);

        $response = app(RagClient::class)->ask('What does zxqvalpha source say?', 5);

        $this->assertSame('deepseek-chat-extractive-fallback', $response['model']);
        $this->assertStringContainsString('strongest retrieved points', $response['answer']);
        $this->assertStringContainsString('preparing the team', $response['answer']);
    }

    public function test_hybrid_search_continues_after_malformed_search_response(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');
        config()->set('rag.chat.provider', 'deepseek');
        config()->set('rag.chat.base_url', 'https://api.deepseek.com');
        config()->set('rag.chat.api_key', 'test-key');
        config()->set('rag.chat.model', 'deepseek-chat');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '127.0.0.1:8001/search')) {
                $query = $request->data()['question'] ?? '';

                if ($query === 'tell me more about oxygen therapy') {
                    return Http::response('not-json', 200);
                }

                return Http::response([
                    'sources' => [
                        [
                            'document' => 'Oxygen Therapy',
                            'locator_type' => 'slide',
                            'locator' => 8,
                            'content' => 'Oxygen is titrated every 15-30 minutes based on SpO2 and clinical stability.',
                        ],
                    ],
                ]);
            }

            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'Oxygen therapy should be titrated using SpO2 and clinical stability [1].']],
                ],
                'model' => 'deepseek-chat',
            ]);
        });

        $response = app(RagClient::class)->ask('tell me more about oxygen therapy', 5);

        $this->assertSame('deepseek-chat', $response['model']);
        $this->assertStringContainsString('Oxygen therapy should be titrated', $response['answer']);
        $this->assertTrue(collect($response['citations'])->contains(
            fn (array $source): bool => str_contains(strtolower((string) ($source['document'] ?? '')), 'oxygen')
                || str_contains(strtolower((string) ($source['content'] ?? '')), 'oxygen')
        ));
    }

    public function test_hybrid_uses_curriculum_context_when_search_only_returns_module_title(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');
        config()->set('rag.chat.provider', 'deepseek');
        config()->set('rag.chat.base_url', 'https://api.deepseek.com');
        config()->set('rag.chat.api_key', 'test-key');
        config()->set('rag.chat.model', 'deepseek-chat');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '127.0.0.1:8001/search')) {
                return Http::response([
                    'sources' => [
                        [
                            'document' => 'Newborn Mentorship Manual',
                            'locator_type' => 'page',
                            'locator' => 2,
                            'content' => 'Based on the excerpt you shared, I can see that oxygen therapy is the topic of Module 4. That is really all the excerpt tells us for now — it gives the title but not a definition or explanation of what oxygen therapy involves.',
                        ],
                    ],
                ]);
            }

            return Http::response([
                'choices' => [
                    ['message' => ['content' => '{"queries":["oxygen therapy","neonatal oxygen therapy","safe oxygen use pulse oximetry"]}']],
                ],
                'model' => 'deepseek-chat',
            ]);
        });

        $response = app(RagClient::class)->ask('oxygen therapy, what is it?', 5);

        $this->assertSame('local-curriculum', $response['model']);
        $this->assertStringContainsString('safe use of supplemental oxygen', $response['answer']);
        $this->assertTrue(collect($response['citations'])->contains(
            fn (array $source): bool => str_contains((string) ($source['content'] ?? ''), 'indications and safe use of oxygen')
        ));
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'api.deepseek.com/chat/completions')
            && ($request->data()['max_tokens'] ?? null) !== 220);
    }

    public function test_hybrid_prefers_slide_sources_over_curriculum_fallback(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');
        config()->set('rag.chat.provider', 'deepseek');
        config()->set('rag.chat.base_url', 'https://api.deepseek.com');
        config()->set('rag.chat.api_key', 'test-key');
        config()->set('rag.chat.model', 'deepseek-chat');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '127.0.0.1:8001/search')) {
                return Http::response([
                    'sources' => [
                        [
                            'document' => 'Module 4 Oxygen Therapy Slides',
                            'locator_type' => 'slide',
                            'locator' => 12,
                            'content' => 'Oxygen therapy slide content: give supplemental oxygen when indicated, use pulse oximetry to monitor oxygen saturation, choose an appropriate delivery device, prescribe oxygen, and monitor response.',
                        ],
                    ],
                ]);
            }

            if (($request->data()['max_tokens'] ?? null) === 220) {
                return Http::response([
                    'choices' => [
                        ['message' => ['content' => '{"queries":["oxygen therapy","pulse oximetry oxygen saturation","oxygen delivery devices"]}']],
                    ],
                    'model' => 'deepseek-chat',
                ]);
            }

            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'From the slides, oxygen therapy means giving supplemental oxygen when indicated, monitoring saturation with pulse oximetry, choosing a delivery device, prescribing oxygen, and monitoring response [1].']],
                ],
                'model' => 'deepseek-chat',
            ]);
        });

        $response = app(RagClient::class)->ask('oxygen therapy, what is it?', 5);

        $this->assertSame('deepseek-chat', $response['model']);
        $this->assertStringContainsString('From the slides', $response['answer']);
        $this->assertSame('Module 4 Oxygen Therapy Slides', $response['citations'][0]['document']);
        $this->assertFalse(collect($response['citations'])->contains(
            fn (array $source): bool => ($source['source_origin'] ?? null) === 'curriculum'
        ));
    }

    public function test_hybrid_maps_hypothermia_follow_up_to_thermoregulation_curriculum(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');
        config()->set('rag.chat.provider', 'deepseek');
        config()->set('rag.chat.base_url', 'https://api.deepseek.com');
        config()->set('rag.chat.api_key', 'test-key');
        config()->set('rag.chat.model', 'deepseek-chat');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '127.0.0.1:8001/search')) {
                return Http::response(['sources' => []]);
            }

            return Http::response([
                'choices' => [
                    ['message' => ['content' => '{"queries":["hypothermia","neonatal thermoregulation","radiant warmer incubator temperature"]}']],
                ],
                'model' => 'deepseek-chat',
            ]);
        });

        $response = app(RagClient::class)->ask('how about hypothermia', 5);

        $this->assertSame('local-curriculum', $response['model']);
        $this->assertStringContainsString('newborn is too cold', $response['answer']);
        $this->assertTrue(collect($response['citations'])->contains(
            fn (array $source): bool => str_contains((string) ($source['content'] ?? ''), 'Module 5: Neonatal Thermoregulation')
        ));
        $this->assertSame('standard', $response['token_usage']['retrieval_trace']['profile']);
        $this->assertSame(5, $response['token_usage']['retrieval_trace']['top_k']);
        $this->assertFalse($response['token_usage']['retrieval_trace']['use_query_planner']);
        $this->assertContains('neonatal thermoregulation', $response['token_usage']['retrieval_trace']['primary_queries']);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'api.deepseek.com/chat/completions'));
    }

    public function test_hybrid_answers_curriculum_module_duration_and_session_breakdown_locally(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');
        config()->set('rag.chat.provider', 'deepseek');
        config()->set('rag.chat.base_url', 'https://api.deepseek.com');
        config()->set('rag.chat.api_key', 'test-key');
        config()->set('rag.chat.model', 'deepseek-chat');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '127.0.0.1:8001/search')) {
                return Http::response([
                    'sources' => [
                        [
                            'document' => 'Unrelated slide',
                            'locator_type' => 'slide',
                            'locator' => 3,
                            'content' => 'This is a long but unrelated retrieved slide about assessment, monitoring, documentation, and general mentorship workflow content that should not be used for resuscitation timing.',
                        ],
                    ],
                ]);
            }

            return Http::response([
                'choices' => [
                    ['message' => ['content' => '{"queries":["Module 6 Newborn Resuscitation","newborn resuscitation sessions duration","resuscitation video algorithm skills teaching practicum case scenarios"]}']],
                ],
                'model' => 'deepseek-chat',
            ]);
        });

        $response = app(RagClient::class)->ask('resuscitation module should take how long and what is the breakdown of the sessions', 5);

        $this->assertSame('local-curriculum', $response['model']);
        $this->assertStringContainsString('Module 6: Newborn Resuscitation takes 135 minutes total', $response['answer']);
        $this->assertStringContainsString('Resuscitation video following algorithm - 15 minutes', $response['answer']);
        $this->assertStringContainsString('Skills teaching and practicum - 60 minutes', $response['answer']);
        $this->assertStringContainsString('Case scenarios on neonatal resuscitation - 60 minutes', $response['answer']);
        $this->assertTrue(collect($response['citations'])->contains(
            fn (array $source): bool => ($source['source_origin'] ?? null) === 'curriculum'
                && str_contains((string) ($source['content'] ?? ''), 'Module 6: Newborn Resuscitation')
        ));
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'api.deepseek.com/chat/completions')
            && ($request->data()['max_tokens'] ?? null) !== 220);
    }

    public function test_retrieval_profiles_route_fast_standard_and_deep_questions(): void
    {
        $client = app(RagClient::class);
        $method = new \ReflectionMethod($client, 'retrievalProfile');
        $method->setAccessible(true);

        $fast = $method->invoke($client, 'what does emonc stand for?', 5);
        $standard = $method->invoke($client, 'oxygen therapy, what is it?', 5);
        $deep = $method->invoke($client, 'tell me more about care of preterms', 5);
        $clinical = $method->invoke($client, 'An 8 day old neonate has been brought from home with a history of inability to breastfeed for 2 days, has hotness of body, and mother reports the baby has not passed urine for the past 1 day. What are some of the actions required, detail all those?', 1);

        $this->assertSame('fast', $fast['name']);
        $this->assertLessThanOrEqual(3, $fast['top_k']);
        $this->assertSame('standard', $standard['name']);
        $this->assertSame('deep', $deep['name']);
        $this->assertGreaterThan($standard['top_k'], $deep['top_k']);
        $this->assertTrue($deep['allow_second_pass']);
        $this->assertSame('composite', $clinical['name']);
        $this->assertSame(7, $clinical['top_k']);
        $this->assertTrue($clinical['use_query_planner']);

        $queries = new \ReflectionMethod($client, 'primarySearchQueries');
        $queries->setAccessible(true);
        $compositeQueries = $queries->invoke($client, 'An 8 day old neonate has been brought from home with a history of inability to breastfeed for 2 days, has hotness of body, and mother reports the baby has not passed urine for the past 1 day. What are some of the actions required, detail all those?');
        $this->assertContains('inability to breastfeed for 2 days', $compositeQueries);
        $this->assertContains('hotness of body', $compositeQueries);
    }

    public function test_sepsis_queries_and_ranking_prefer_sepsis_sources_over_unrelated_media(): void
    {
        $client = app(RagClient::class);

        $queries = new \ReflectionMethod($client, 'primarySearchQueries');
        $queries->setAccessible(true);

        $rank = new \ReflectionMethod($client, 'prioritizeContentSources');
        $rank->setAccessible(true);

        $primaryQueries = $queries->invoke($client, 'if sepsis is high what are some of the things we can do?');

        $this->assertContains('neonatal sepsis danger signs management', $primaryQueries);
        $this->assertContains('sepsis evaluation antibiotics antimicrobial therapy', $primaryQueries);

        $sources = $rank->invoke($client, [
            [
                'document' => 'Module 19. Neonatal Jaundice',
                'locator_type' => 'slide',
                'locator' => 13,
                'content' => str_repeat('Filtered sunlight phototherapy and unrelated jaundice content. ', 20),
                'media' => [['filename' => 'slide-13-image-1.png']],
                'retrieval_rank' => 1,
            ],
            [
                'document' => 'Module 11. Danger Signs and Neonatal Sepsis',
                'locator_type' => 'slide',
                'locator' => 8,
                'content' => 'Neonatal sepsis danger signs require urgent assessment, sepsis evaluation, antibiotics, monitoring, and referral when needed.',
                'media' => [],
                'retrieval_rank' => 4,
            ],
        ], 2, 'if sepsis is high what are some of the things we can do?', false);

        $this->assertSame('Module 11. Danger Signs and Neonatal Sepsis', $sources[0]['document']);
    }

    public function test_top_k_is_clamped(): void
    {
        config()->set('rag.top_k.min', 1);
        config()->set('rag.top_k.max', 10);

        $client = app(RagClient::class);

        $this->assertSame(1, $client->clampTopK(0));
        $this->assertSame(10, $client->clampTopK(99));
    }

    public function test_short_sections_are_not_chunked_one_character_at_a_time(): void
    {
        config()->set('rag.chunking.max_chars', 3500);
        config()->set('rag.chunking.overlap_chars', 400);

        $chunks = app(DocumentTextExtractor::class)->chunk([
            [
                'locator_type' => 'slide',
                'locator' => '1',
                'content' => 'Introduction to care of the preterm infant',
            ],
        ]);

        $this->assertCount(1, $chunks);
        $this->assertSame('Introduction to care of the preterm infant', $chunks[0]['content']);
    }
}
