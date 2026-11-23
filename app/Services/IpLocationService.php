<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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

        $cacheDays = max(1, (int) config('services.ip_geolocation.cache_days', 30));
        $now = now();

        try {
            if (Schema::hasTable('audit_ip_locations')) {
                $cached = DB::table('audit_ip_locations')
                    ->where('ip_address', $ip)
                    ->where(function ($query) use ($now): void {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                    })
                    ->first(['location_label', 'latitude', 'longitude']);

                if ($cached !== null) {
                    return $cached->location_label === null ? null : [
                        'source' => 'ip',
                        'label' => $cached->location_label,
                        'latitude' => $cached->latitude !== null ? (float) $cached->latitude : null,
                        'longitude' => $cached->longitude !== null ? (float) $cached->longitude : null,
                        'accuracy_meters' => null,
                    ];
                }
            }
        } catch (Throwable) {
            // Continue to the provider if the optional cache table is unavailable.
        }

        $location = $this->lookupProvider($ip);

        try {
            if (Schema::hasTable('audit_ip_locations')) {
                DB::table('audit_ip_locations')->updateOrInsert(
                    ['ip_address' => $ip],
                    [
                        'location_label' => $location['label'] ?? null,
                        'latitude' => $location['latitude'] ?? null,
                        'longitude' => $location['longitude'] ?? null,
                        'expires_at' => now()->addDays($cacheDays),
                        'updated_at' => now(),
                        'created_at' => $now,
                    ],
                );
            }
        } catch (Throwable) {
            // Location enrichment must never block the audited request.
        }

        return $location;
    }

    private function lookupProvider(string $ip): ?array
    {
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
    }
}
