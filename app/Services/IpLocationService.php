<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class IpLocationService
{
    public function resolve(?string $ip): ?array
    {
        $ip = trim((string) $ip);

        if (! config('services.ip_geolocation.enabled', false)
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        $cacheKey = 'audit-ip-location:'.hash('sha256', $ip);
        $cacheDays = max(1, (int) config('services.ip_geolocation.cache_days', 30));

        return Cache::remember($cacheKey, now()->addDays($cacheDays), function () use ($ip): ?array {
            try {
                $baseUrl = rtrim((string) config('services.ip_geolocation.base_url', 'https://ipwho.is'), '/');
                $timeout = max(1, (int) config('services.ip_geolocation.timeout', 2));
                $response = Http::acceptJson()
                    ->connectTimeout(min($timeout, 2))
                    ->timeout($timeout)
                    ->get($baseUrl.'/'.rawurlencode($ip), [
                        'fields' => 'country,region,city,latitude,longitude',
                    ]);

                if (! $response->successful()) {
                    return null;
                }

                $payload = $response->json();
                if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
                    return null;
                }

                $label = implode(', ', array_values(array_filter([
                    trim((string) ($payload['city'] ?? '')),
                    trim((string) ($payload['region'] ?? '')),
                    trim((string) ($payload['country'] ?? '')),
                ])));

                if ($label === '') {
                    return null;
                }

                return [
                    'source' => 'ip',
                    'label' => $label,
                    'latitude' => is_numeric($payload['latitude'] ?? null) ? (float) $payload['latitude'] : null,
                    'longitude' => is_numeric($payload['longitude'] ?? null) ? (float) $payload['longitude'] : null,
                    'accuracy_meters' => null,
                ];
            } catch (Throwable) {
                // Location enrichment must never block the audited request.
                return null;
            }
        });
    }
}
