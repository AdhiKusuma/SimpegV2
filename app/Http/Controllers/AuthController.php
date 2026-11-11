<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbUser;
use App\Services\Sso\SsoClient;
use App\Services\Sso\SsoException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    private const AUTH_MARKER_COOKIE = 'simpeg-authenticated';

    public function __construct(private readonly SsoClient $ssoClient) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->session()->has('id_user')) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login', [
            'appName' => config('app.name', 'SIMPEG'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'id_user' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string'],
        ]);

        $loginInput = trim($credentials['id_user']);
        $ssoEnabled = (bool) config('services.sso.enabled', false);
        $ssoToken = null;
        $ssoProfile = null;

        if ($ssoEnabled) {
            try {
                $sso = $this->ssoClient->authenticate($loginInput, $credentials['password']);
            } catch (SsoException $exception) {
                return back()->withErrors([
                    'id_user' => $exception->getMessage(),
                ])->onlyInput('id_user');
            }

            $ssoToken = $sso['token'];
            $ssoProfile = $sso['profile'];
            $user = $this->resolveSsoUser($ssoProfile);

            if (! $user) {
                return back()->withErrors([
                    'id_user' => 'Pegawai SSO belum terdaftar sebagai pegawai aktif di SIMPEG lokal. Hubungi Admin.',
                ])->onlyInput('id_user');
            }
        } else {
            $user = TbUser::query()
                ->where(function ($query) use ($loginInput): void {
                    $query->where('id_user', $loginInput)
                        ->orWhere('id_pegawai', $loginInput);
                })
                ->first();

            if (! $user || ! $this->passwordMatches($user->password, $credentials['password'])) {
                $user = null;
            }
        }

        if (! $user) {
            return back()->withErrors([
                'id_user' => 'Username/ID Pegawai atau password salah.',
            ])->onlyInput('id_user');
        }

        if ($user->status_aktif === 'N') {
            return back()->withErrors([
                'id_user' => 'Akun Anda dinonaktifkan. Hubungi Admin.',
            ])->onlyInput('id_user');
        }

        $role = strtolower((string) $user->hak_akses);
        if (! in_array($role, ['admin', 'superadmin', 'kepala', 'user'], true)) {
            return back()->withErrors([
                'id_user' => 'Akun belum memiliki hak akses yang valid. Hubungi Admin.',
            ])->onlyInput('id_user');
        }

        if (! $ssoEnabled) {
            $this->upgradePasswordHash($user, $credentials['password']);
        }

        $request->session()->regenerate();

        $sessionData = [
            'id_user' => $user->id_user,
            'nama_user' => $user->nama_user,
            'hak_akses' => $role,
            'id_pegawai' => $user->id_pegawai,
        ];

        if ($ssoToken !== null && is_array($ssoProfile)) {
            $sessionData['sso_token'] = $ssoToken;
            $sessionData['sso_employee_id'] = $ssoProfile['employee_id'];
        }

        $request->session()->put($sessionData);

        if ($role === 'kepala') {
            $request->session()->put('kode_kantor', TbJabatan::query()
                ->where('id_peg', $user->id_pegawai)
                ->where('status_jab', 'Aktif')
                ->value('unit_kerja') ?? '-');
        }

        $request->session()->flash('welcome', true);

        $response = match ($role) {
            'kepala' => redirect()->route('dashboard'),
            'user' => redirect()->route('profile'),
            'admin', 'superadmin' => redirect()->route('dashboard'),
            default => redirect()->route('login'),
        };

        return $response->withCookie(cookie(
            self::AUTH_MARKER_COOKIE,
            '1',
            60 * 24 * 365,
            config('session.path', '/'),
            config('session.domain'),
            (bool) config('session.secure', false),
            (bool) config('session.http_only', true),
            false,
            config('session.same_site', 'lax'),
        ));
    }

    /**
     * SSO is the source of truth for credentials. SIMPEG only keeps a local
     * shadow account for role, profile, payroll, and notification relations.
     */
    private function resolveSsoUser(array $profile): ?TbUser
    {
        $employeeId = trim((string) ($profile['employee_id'] ?? ''));
        if ($employeeId === '') {
            return null;
        }

        $existing = TbUser::query()->where('id_pegawai', $employeeId)->first();
        if ($existing) {
            return $existing;
        }

        $employee = TbPegawai::query()->where('id_peg', $employeeId)->first();
        if (! $employee || ! in_array(strtoupper((string) $employee->status_aktif), ['1', 'Y'], true)) {
            return null;
        }

        $userId = $this->nextSsoUserId($employeeId);
        $name = trim((string) ($profile['full_name'] ?? '')) ?: trim((string) $employee->nama);

        return TbUser::query()->create([
            'id_user' => $userId,
            'nama_user' => Str::limit($name, 64, ''),
            'password' => Hash::make(Str::random(64)),
            'hak_akses' => 'User',
            'status_aktif' => 'Y',
            'id_pegawai' => $employeeId,
            'created_by' => 'SSO',
            'updated_by' => 'SSO',
        ]);
    }

    private function nextSsoUserId(string $employeeId): string
    {
        $candidate = Str::limit($employeeId, 10, '');
        if (! TbUser::query()->where('id_user', $candidate)->exists()) {
            return $candidate;
        }

        do {
            $candidate = 'S'.strtoupper(Str::random(9));
        } while (TbUser::query()->where('id_user', $candidate)->exists());

        return $candidate;
    }

    private function passwordMatches(?string $storedPassword, string $plainPassword): bool
    {
        $storedPassword = trim((string) $storedPassword);

        if ($storedPassword === '') {
            return false;
        }

        if (preg_match('/^[a-f0-9]{32}$/i', $storedPassword) === 1) {
            return hash_equals(strtolower($storedPassword), md5($plainPassword));
        }

        if ((password_get_info($storedPassword)['algo'] ?? 0) === 0) {
            return false;
        }

        return Hash::check($plainPassword, $storedPassword);
    }

    private function upgradePasswordHash(TbUser $user, string $plainPassword): void
    {
        $storedPassword = trim((string) $user->password);

        if (preg_match('/^[a-f0-9]{32}$/i', $storedPassword) === 1 || Hash::needsRehash($storedPassword)) {
            $user->forceFill(['password' => Hash::make($plainPassword)])->saveQuietly();
        }
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->flush();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withoutCookie(self::AUTH_MARKER_COOKIE);
    }
}
