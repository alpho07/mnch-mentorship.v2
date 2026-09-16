<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpGeolocationService
{
    private const CACHE_PREFIX = 'ip_geo:';

    private const CACHE_TTL_SUCCESS = 60 * 60 * 24 * 30;

    private const CACHE_TTL_FAILURE = 60 * 60;

    public function resolve(?string $ip): ?string
    {
        if ($ip === null || $ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'Local network';
        }

        $key = self::CACHE_PREFIX.$ip;

        $cached = cache()->get($key);

        if ($cached !== null) {
            return $cached === '__unresolved__' ? null : $cached;
        }

        try {
            $response = Http::timeout(3)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,city,country',
                ]);

            if ($response->failed() || $response->json('status') !== 'success') {
                cache()->put($key, '__unresolved__', self::CACHE_TTL_FAILURE);

                return null;
            }

            $city = $response->json('city');
            $country = $response->json('country');

            $location = collect([$city, $country])->filter()->implode(', ');

            if ($location === '') {
                cache()->put($key, '__unresolved__', self::CACHE_TTL_FAILURE);

                return null;
            }

            cache()->put($key, $location, self::CACHE_TTL_SUCCESS);

            return $location;
        } catch (\Throwable $e) {
            Log::warning('IP geolocation lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);
            cache()->put($key, '__unresolved__', self::CACHE_TTL_FAILURE);

            return null;
        }
    }
}
