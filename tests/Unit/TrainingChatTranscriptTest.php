<?php

namespace Tests\Unit;

use App\Models\Training;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingChatTranscriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_append_chat_transcript_appends_to_an_empty_column(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['chat_setup_transcript' => null]);

        $training->appendChatTranscript(['role' => 'bot', 'text' => 'Welcome!']);

        $this->assertSame(
            [['role' => 'bot', 'text' => 'Welcome!']],
            $training->fresh()->chat_setup_transcript
        );
    }

    public function test_append_chat_transcript_appends_to_an_existing_column(): void
    {
        $training = Training::factory()->facilityMentorship()->create([
            'chat_setup_transcript' => [['role' => 'bot', 'text' => 'Welcome!']],
        ]);

        $training->appendChatTranscript(['role' => 'user', 'text' => 'Live Mentorship']);

        $this->assertSame(
            [
                ['role' => 'bot', 'text' => 'Welcome!'],
                ['role' => 'user', 'text' => 'Live Mentorship'],
            ],
            $training->fresh()->chat_setup_transcript
        );
    }

    public function test_guided_setup_method_column_accepts_wizard_and_chat(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['guided_setup_method' => 'chat']);

        $this->assertSame('chat', $training->fresh()->guided_setup_method);
    }
}
