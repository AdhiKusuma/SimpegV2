<?php

namespace App\Services\Sso;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SsoClient
{
    public function authenticate(string $employeeId, string $password): array
    {
        try {
            $loginResponse = $this->request()->post('/api/auth/login', [
                'id_peg' => $employeeId,
                'password' => $password,
                'app' => (string) config('services.sso.app', 'simpeg'),
            ]);
        } catch (ConnectionException $exception) {
            throw new SsoException('Layanan SSO tidak dapat dihubungi.', 0, $exception);
        }

        $this->guardLoginResponse($loginResponse);

        $token = data_get($loginResponse->json(), 'data.token');

        if (! is_string($token) || trim($token) === '') {
            throw new SsoException('Respons login SSO tidak valid.');
        }

        try {
            $profileResponse = $this->request()
                ->withToken($token)
                ->get('/api/auth/whoami');
        } catch (ConnectionException $exception) {
            throw new SsoException('Layanan SSO tidak dapat dihubungi.', 0, $exception);
        }

        $this->guardProfileResponse($profileResponse);

        $profile = $profileResponse->json('data');
        $resolvedEmployeeId = is_array($profile) ? trim((string) ($profile['employee_id'] ?? '')) : '';

        if ($resolvedEmployeeId === '') {
            throw new SsoException('Respons profil SSO tidak memiliki ID pegawai.');
        }

        return [
            'token' => $token,
            'profile' => [
                'employee_id' => $resolvedEmployeeId,
                'full_name' => trim((string) ($profile['full_name'] ?? '')),
                'branch_name' => trim((string) ($profile['branch_name'] ?? '')),
                'job_position' => trim((string) ($profile['job_position'] ?? '')),
            ],
        ];
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout(max(1, (int) config('services.sso.connect_timeout', 5)))
            ->timeout(max(1, (int) config('services.sso.timeout', 10)))
            ->withOptions([
                'verify' => (bool) config('services.sso.verify', true),
            ])
            ->baseUrl(rtrim((string) config('services.sso.base_url'), '/'));
    }

    private function guardLoginResponse(Response $response): void
    {
        if ($response->status() === 401) {
            throw new SsoException('ID Pegawai atau password SSO salah.');
        }

        if ($response->status() === 403) {
            throw new SsoException('Akun tidak memiliki akses ke SIMPEG.');
        }

        if ($response->failed()) {
            throw new SsoException('Layanan SSO sedang tidak tersedia.');
        }
    }

    private function guardProfileResponse(Response $response): void
    {
        if ($response->status() === 401) {
            throw new SsoException('Token SSO tidak valid atau sudah kedaluwarsa.');
        }

        if ($response->failed()) {
            throw new SsoException('Profil dari layanan SSO tidak dapat dibaca.');
        }
    }
}
