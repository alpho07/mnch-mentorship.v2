<?php

namespace Tests\Unit\Services;

use App\Services\IpGeolocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IpGeolocationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_for_empty_or_invalid_ips(): void
    {
        $service = app(IpGeolocationService::class);

        $this->assertNull($service->resolve(null));
        $this->assertNull($service->resolve(''));
        $this->assertNull($service->resolve('not-an-ip'));
    }

    public function test_private_and_reserved_ips_resolve_to_local_network(): void
    {
        $service = app(IpGeolocationService::class);

        $this->assertSame('Local network', $service->resolve('127.0.0.1'));
        $this->assertSame('Local network', $service->resolve('192.168.1.10'));
        $this->assertSame('Local network', $service->resolve('10.0.0.5'));
    }

    public function test_public_ip_is_resolved_via_api(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'city' => 'Nairobi',
                'country' => 'Kenya',
            ]),
        ]);

        $location = app(IpGeolocationService::class)->resolve('197.232.60.1');

        $this->assertSame('Nairobi, Kenya', $location);
    }

    public function test_successful_lookups_are_cached(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'city' => 'Nairobi',
                'country' => 'Kenya',
            ]),
        ]);

        $service = app(IpGeolocationService::class);

        $this->assertSame('Nairobi, Kenya', $service->resolve('197.232.60.1'));

        Http::fake([
            'ip-api.com/*' => Http::response([], 500),
        ]);

        $this->assertSame('Nairobi, Kenya', $service->resolve('197.232.60.1'));
    }

    public function test_failed_api_response_caches_a_short_lived_miss(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response(['status' => 'fail'], 200),
        ]);

        $location = app(IpGeolocationService::class)->resolve('8.8.8.8');

        $this->assertNull($location);
        $this->assertTrue(cache()->has('ip_geo:8.8.8.8'));
    }
}
