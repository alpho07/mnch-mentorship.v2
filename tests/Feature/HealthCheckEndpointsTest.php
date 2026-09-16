<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_laravel_default_up_route_reports_healthy(): void
    {
        $this->get('/up')->assertSuccessful();
    }

    public function test_the_public_health_route_returns_ok_status_with_expected_keys(): void
    {
        $response = $this->get('/health');

        $response->assertSuccessful();
        $response->assertJson(['status' => 'ok']);
        $response->assertJsonStructure(['status', 'timestamp', 'resources_count', 'categories_count']);
    }

    public function test_the_api_v1_health_route_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertSuccessful();
    }
}
