<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class SetupController extends Controller
{
    private const DRIVERS = ['mysql', 'pgsql', 'oracle'];

    public static function installed(): bool
    {
        return File::exists(storage_path('app/installed'));
    }

    public function database(): View
    {
        return view('setup.database', [
            'selected' => session('setup.driver', 'mysql'),
        ]);
    }

    public function storeDatabase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'driver' => ['required', 'in:'.implode(',', self::DRIVERS)],
        ]);

        $request->session()->forget('setup.connection');
        $request->session()->put('setup.driver', $validated['driver']);

        return redirect()->route('setup.connection');
    }

    public function connection(Request $request): View|RedirectResponse
    {
        $driver = $request->session()->get('setup.driver');

        if (! in_array($driver, self::DRIVERS, true)) {
            return redirect()->route('setup.database');
        }

        return view('setup.connection', [
            'driver' => $driver,
            'defaults' => $request->session()->get('setup.connection', [
                'host' => '127.0.0.1',
                'port' => $driver === 'pgsql' ? '5432' : ($driver === 'oracle' ? '1521' : '3306'),
                'database' => '',
                'service_name' => '',
                'username' => '',
            ]),
        ]);
    }

    public function storeConnection(Request $request): RedirectResponse
    {
        $driver = $request->session()->get('setup.driver');

        if (! in_array($driver, self::DRIVERS, true)) {
            return redirect()->route('setup.database');
        }

        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:128'],
            'service_name' => [$driver === 'oracle' ? 'required' : 'nullable', 'string', 'max:128'],
            'username' => ['required', 'string', 'max:128'],
            'password' => ['nullable', 'string', 'max:1024'],
        ]);

        $connection = array_merge($validated, ['driver' => $driver]);

        try {
            $this->connect($connection);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except('password'))
                ->withErrors([
                    'connection' => $this->connectionError($driver, $exception),
                ]);
        }

        $request->session()->put('setup.connection', $connection);

        return redirect()->route('setup.admin');
    }

    public function admin(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('setup.connection')) {
            return redirect()->route('setup.connection');
        }

        return view('setup.admin');
    }

    public function finish(Request $request): RedirectResponse
    {
        $connection = $request->session()->get('setup.connection');

        if (! is_array($connection)) {
            return redirect()->route('setup.connection');
        }

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        if (! File::exists(base_path('.env')) || ! is_writable(base_path('.env'))) {
            return back()->withInput($request->only('email'))->withErrors([
                'installation' => 'The .env file is missing or not writable by the web server.',
            ]);
        }

        try {
            $this->connect($connection);
            Artisan::call('migrate', [
                '--database' => 'setup',
                '--force' => true,
            ]);

            $user = User::on('setup')->updateOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => 'Administrator',
                    'password' => $validated['password'],
                    'is_admin' => true,
                    'email_verified_at' => now(),
                ],
            );

            $agentToken = Str::random(64);
            $this->writeEnvironment($connection, $agentToken);
            File::put(storage_path('app/installed'), now()->toIso8601String());
            Artisan::call('config:clear');
            Auth::login($user);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput($request->only('email'))->withErrors([
                'installation' => 'Setup could not be completed. '.$this->safeMessage($exception),
            ]);
        }

        $request->session()->forget('setup');

        return redirect()->route('dashboard')
            ->with('status', 'Installation completed successfully.')
            ->with('agent_token', $agentToken);
    }

    private function connect(array $connection): void
    {
        $driver = $connection['driver'];
        $base = config("database.connections.{$driver}", []);

        config([
            'database.connections.setup' => array_merge($base, $connection),
        ]);

        DB::purge('setup');
        DB::connection('setup')->getPdo();
    }

    private function writeEnvironment(array $connection, string $agentToken): void
    {
        $values = [
            'DB_CONNECTION' => $connection['driver'],
            'DB_HOST' => $connection['host'],
            'DB_PORT' => $connection['port'],
            'DB_DATABASE' => $connection['database'],
            'DB_SERVICE_NAME' => $connection['service_name'] ?? '',
            'DB_USERNAME' => $connection['username'],
            'DB_PASSWORD' => $connection['password'] ?? '',
            'SESSION_DRIVER' => 'file',
            'AGENT_API_TOKEN' => $agentToken,
        ];

        $contents = File::get(base_path('.env'));

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->environmentValue((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';

            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, $line, $contents)
                : rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        File::put(base_path('.env'), $contents, true);
    }

    private function environmentValue(string $value): string
    {
        return '"'.str_replace(
            ['\\', '"', '$', "\r", "\n"],
            ['\\\\', '\\"', '\\$', '', '\\n'],
            $value,
        ).'"';
    }

    private function connectionError(string $driver, Throwable $exception): string
    {
        if ($driver === 'oracle' && ! extension_loaded('oci8')) {
            return 'Oracle support is not installed. Install Oracle Instant Client and the PHP OCI8 extension, then try again.';
        }

        return 'We could not connect to the database. Check the host, port, database, credentials, and network access. '.$this->safeMessage($exception);
    }

    private function safeMessage(Throwable $exception): string
    {
        $message = preg_replace('/password=[^\\s;]+/i', 'password=***', $exception->getMessage());

        return mb_substr((string) $message, 0, 300);
    }
}
