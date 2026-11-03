<?php

namespace Tests\Unit;

use App\Services\Sso\SsoClient;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoClientTest extends TestCase
{
    public function test_it_authenticates_and_reads_the_sso_profile(): void
    {
        config([
            'services.sso.base_url' => 'https://sso.test',
            'services.sso.app' => 'simpeg',
        ]);

        Http::fake([
            'https://sso.test/api/auth/login' => Http::response([
                'status' => 200,
                'data' => ['token' => 'jwt-token'],
            ]),
            'https://sso.test/api/auth/whoami' => Http::response([
                'status' => 200,
                'data' => [
                    'employee_id' => '102-119',
                    'full_name' => 'Nama Pegawai',
                    'branch_name' => 'Kantor Cabang',
                    'job_position' => 'Staff',
                ],
            ]),
        ]);

        $result = app(SsoClient::class)->authenticate('102-119', 'secret');

        $this->assertSame('jwt-token', $result['token']);
        $this->assertSame('102-119', $result['profile']['employee_id']);
        $this->assertSame('Nama Pegawai', $result['profile']['full_name']);

        Http::assertSent(function (HttpRequest $request): bool {
            return $request->url() === 'https://sso.test/api/auth/login'
                && $request->data() === [
                    'id_peg' => '102-119',
                    'password' => 'secret',
                    'app' => 'simpeg',
                ];
        });

        Http::assertSent(function (HttpRequest $request): bool {
            return $request->url() === 'https://sso.test/api/auth/whoami'
                && $request->hasHeader('Authorization', 'Bearer jwt-token');
        });
    }

    public function test_it_returns_a_safe_message_when_sso_rejects_credentials(): void
    {
        config(['services.sso.base_url' => 'https://sso.test']);

        Http::fake([
            'https://sso.test/api/auth/login' => Http::response(['status' => 401], 401),
        ]);

        $this->expectExceptionMessage('ID Pegawai atau password SSO salah.');

        app(SsoClient::class)->authenticate('102-119', 'wrong');
    }
}
