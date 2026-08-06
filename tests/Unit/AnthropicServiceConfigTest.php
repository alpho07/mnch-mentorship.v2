<?php

namespace Tests\Unit;

use Tests\TestCase;

class AnthropicServiceConfigTest extends TestCase
{
    public function test_the_anthropic_config_array_is_declared_in_services_config(): void
    {
        $this->assertIsArray(
            config('services.anthropic'),
            'config/services.php previously had no "anthropic" array at all (Phase 1 risk 9.7), so '
            . 'config(\'services.anthropic\') resolved null unconditionally — Api\ChatController::assistant() '
            . 'was silently non-functional regardless of any ANTHROPIC_API_KEY env var.'
        );
        $this->assertArrayHasKey('api_key', config('services.anthropic'));
    }

    public function test_the_api_key_is_read_from_the_anthropic_api_key_env_var(): void
    {
        putenv('ANTHROPIC_API_KEY=test-key-value');
        $freshConfig = require config_path('services.php');

        $this->assertSame('test-key-value', $freshConfig['anthropic']['api_key']);

        putenv('ANTHROPIC_API_KEY');
    }
}
