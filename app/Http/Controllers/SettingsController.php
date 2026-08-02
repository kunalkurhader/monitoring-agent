<?php

namespace App\Http\Controllers;

use App\Models\BrandingSetting;
use App\Models\BrowserProject;
use App\Models\MailSetting;
use App\Models\WebsiteMonitor;
use App\Support\EnvironmentFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(private readonly EnvironmentFile $environmentFile) {}

    public function index(): View
    {
        return view('settings.index', [
            'browserProjects' => BrowserProject::query()->orderBy('name')->get(),
            'brandingSetting' => BrandingSetting::query()->first(),
            'mailSetting' => MailSetting::query()->first(),
            'websiteMonitors' => WebsiteMonitor::query()->latest()->get(),
        ]);
    }

    public function branding(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('branding', [
            'site_name' => ['required', 'string', 'max:80'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $branding = BrandingSetting::query()->first();
        $oldLogo = $branding?->logo_path;
        $values = ['site_name' => $validated['site_name']];

        if ($request->boolean('remove_logo')) {
            $values += ['logo_path' => null, 'logo_mime' => null];
        } elseif ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $extension = $logo->extension();
            $path = $logo->storeAs('branding', 'logo-'.bin2hex(random_bytes(8)).'.'.$extension, 'local');
            $values += ['logo_path' => $path, 'logo_mime' => $logo->getMimeType()];
        }

        $branding
            ? $branding->update($values)
            : BrandingSetting::query()->create($values);

        if ($oldLogo && ($request->boolean('remove_logo') || isset($values['logo_path']))) {
            Storage::disk('local')->delete($oldLogo);
        }

        return redirect()->to(route('settings.index').'#branding')->with('status', 'Branding updated.');
    }

    public function mail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'scheme' => ['nullable', Rule::in(['smtp', 'smtps'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'from_address' => ['required', 'email:rfc', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);
        $setting = MailSetting::query()->first();
        if (($validated['password'] ?? '') === '') {
            unset($validated['password']);
        }
        $validated['is_enabled'] = $request->boolean('is_enabled');
        $setting ? $setting->update($validated) : MailSetting::query()->create($validated);

        return redirect()->to(route('settings.index').'#email-delivery')->with('status', 'Email delivery settings saved.');
    }

    public function factoryReset(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string'],
            'confirmation' => ['required', Rule::in(['ERASE EVERYTHING'])],
        ], [
            'confirmation.in' => 'Type ERASE EVERYTHING exactly to confirm the factory reset.',
        ]);

        if ($validator->fails()) {
            return redirect()->to(route('settings.index').'#danger-zone')
                ->withErrors($validator, 'factoryReset')
                ->withInput($request->except('password'));
        }

        if (! Hash::check((string) $request->input('password'), (string) $request->user()?->password)) {
            return redirect()->to(route('settings.index').'#danger-zone')
                ->withErrors(['password' => 'The administrator password is incorrect.'], 'factoryReset')
                ->withInput($request->except('password'));
        }

        if (! $this->environmentFile->isWritable()) {
            return redirect()->to(route('settings.index').'#danger-zone')
                ->withErrors(['reset' => 'The .env file is missing or not writable by the web server.'], 'factoryReset');
        }

        $originalEnvironment = $this->environmentFile->contents();

        try {
            $this->environmentFile->replace([
                'DB_CONNECTION' => 'sqlite',
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => '3306',
                'DB_DATABASE' => database_path('database.sqlite'),
                'DB_SERVICE_NAME' => '',
                'DB_USERNAME' => '',
                'DB_PASSWORD' => '',
                'AGENT_API_TOKEN' => '',
                'SESSION_DRIVER' => 'file',
            ]);

            Storage::disk('local')->deleteDirectory('branding');
            $this->eraseApplicationData();
            Auth::logout();
            File::delete(storage_path('app/installed'));
            Artisan::call('config:clear');
        } catch (Throwable $exception) {
            try {
                $this->environmentFile->write($originalEnvironment);
                Artisan::call('config:clear');
            } catch (Throwable) {
                // Preserve the original reset failure for reporting.
            }

            report($exception);

            return redirect()->to(route('settings.index').'#danger-zone')
                ->withErrors(['reset' => 'Factory reset could not be completed. No further reset attempts should be made until the application logs are checked.'], 'factoryReset');
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('setup.database');
    }

    private function eraseApplicationData(): void
    {
        $tables = [
            'website_monitor_alerts',
            'website_monitors',
            'agent_log_chunks',
            'agent_log_files',
            'browser_events',
            'browser_projects',
            'disk_stats',
            'process_stats',
            'system_stats',
            'agents',
            'agent_api_tokens',
            'team_invitations',
            'mail_settings',
            'branding_settings',
            'password_reset_tokens',
            'sessions',
            'failed_jobs',
            'job_batches',
            'jobs',
            'cache_locks',
            'cache',
            'users',
        ];

        DB::transaction(function () use ($tables): void {
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
        });
    }
}
