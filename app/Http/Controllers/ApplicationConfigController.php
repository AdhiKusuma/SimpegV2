<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbConfig;
use App\Services\ApplicationLogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApplicationConfigController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->ensureAdmin($request);

        $config = TbConfig::query()->findOrFail($this->configId($request));

        return Inertia::render('Config/Application', [
            'config' => $this->presentConfig($config),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $config = TbConfig::query()->findOrFail($this->configId($request));
        $data = $request->validate([
            'nama_app' => ['required', 'string', 'max:16'],
            'desc_app' => ['nullable', 'string', 'max:64'],
            'alias_app' => ['nullable', 'string', 'max:3'],
            'url_app' => ['nullable', 'url', 'max:64'],
            'anchor_app' => ['nullable', 'string', 'max:64'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif', 'max:2048'],
            'logo_dark' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif', 'max:2048'],
        ]);

        unset($data['logo']);
        unset($data['logo_dark']);
        if ($request->hasFile('logo')) {
            $newLogo = $this->storeLogo($request, 'logo', $config->logo, 'app_logo_light');
            $data['logo'] = $newLogo;
        }
        if ($request->hasFile('logo_dark')) {
            $newDarkLogo = $this->storeLogo($request, 'logo_dark', $config->logo_dark, 'app_logo_dark');
            $data['logo_dark'] = $newDarkLogo;
        }

        $config->update($data);

        return to_route('config.application.edit', ['id' => $config->id_app])->with('success', 'Konfigurasi aplikasi berhasil diperbarui.');
    }

    public function logo(string $filename): BinaryFileResponse
    {
        abort_unless($filename === basename($filename), 404);

        $paths = [
            public_path('uploads/config/'.$filename),
        ];

        foreach ($paths as $path) {
            if (File::isFile($path)) {
                return response()->file($path);
            }
        }

        abort(404);
    }

    private function configId(Request $request): string
    {
        return $request->string('id')->trim()->value() ?: '1';
    }

    private function presentConfig(TbConfig $config): array
    {
        return [
            'id_app' => $config->id_app,
            'nama_app' => $config->nama_app,
            'desc_app' => $config->desc_app,
            'alias_app' => $config->alias_app,
            'url_app' => $config->url_app,
            'anchor_app' => $config->anchor_app,
            'logo' => $config->logo,
            'logo_url' => app(ApplicationLogoService::class)->url($config->logo),
            'logo_dark' => $config->logo_dark,
            'logo_dark_url' => app(ApplicationLogoService::class)->darkUrl($config->logo_dark),
        ];
    }

    private function storeLogo(Request $request, string $field, ?string $oldLogo, string $prefix): string
    {
        $directory = public_path('uploads/config');
        File::ensureDirectoryExists($directory, 0755);

        $file = $request->file($field);
        $name = $prefix.'_'.now()->format('YmdHisv').'_'.Str::lower(Str::random(8)).'.'.$file->extension();
        $file->move($directory, $name);

        if ($oldLogo && basename($oldLogo) === $oldLogo) {
            $oldPath = $directory.'/'.$oldLogo;
            if ($oldLogo !== $name && File::isFile($oldPath)) {
                File::delete($oldPath);
            }
        }

        return $name;
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(strtolower((string) $request->session()->get('hak_akses')) === 'admin', 403);
    }
}
