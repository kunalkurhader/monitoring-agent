@extends('setup.layout', ['step' => 2])

@section('title', 'Connect your database')

@section('content')
    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-400">Step 2 of 3</p>
    <h2 class="mt-3 text-3xl font-semibold tracking-tight">Connect to {{ ['mysql' => 'MySQL', 'pgsql' => 'PostgreSQL', 'oracle' => 'Oracle'][$driver] }}</h2>
    <p class="mt-3 text-slate-600 dark:text-slate-400">Use any reachable database, including Amazon RDS and other managed services.</p>

    <form method="POST" action="{{ route('setup.connection.store') }}" class="mt-8 space-y-5">
        @csrf
        <div class="grid gap-5 sm:grid-cols-[1fr_9rem]">
            <label class="block">
                <span class="setup-label">Host</span>
                <input name="host" value="{{ old('host', $defaults['host']) }}" placeholder="database.example.com" required class="setup-input">
            </label>
            <label class="block">
                <span class="setup-label">Port</span>
                <input name="port" value="{{ old('port', $defaults['port']) }}" inputmode="numeric" required class="setup-input">
            </label>
        </div>

        <label class="block">
            <span class="setup-label">{{ $driver === 'oracle' ? 'Database / SID' : 'Database name' }}</span>
            <input name="database" value="{{ old('database', $defaults['database']) }}" placeholder="{{ $driver === 'oracle' ? 'ORCL' : 'monitoring' }}" required class="setup-input">
        </label>

        @if ($driver === 'oracle')
            <label class="block">
                <span class="setup-label">Service name</span>
                <input name="service_name" value="{{ old('service_name', $defaults['service_name']) }}" placeholder="ORCLPDB1" required class="setup-input">
            </label>
        @else
            <input type="hidden" name="service_name" value="">
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block">
                <span class="setup-label">Username</span>
                <input name="username" value="{{ old('username', $defaults['username']) }}" autocomplete="username" required class="setup-input">
            </label>
            <label class="block">
                <span class="setup-label">Password</span>
                <input type="password" name="password" autocomplete="current-password" class="setup-input">
            </label>
        </div>

        <div class="flex items-center justify-between pt-3">
            <a href="{{ route('setup.database') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">← Back</a>
            <button class="rounded-xl bg-emerald-400 px-5 py-3 font-semibold text-slate-950 transition hover:bg-emerald-300">
                Test and continue <span aria-hidden="true">→</span>
            </button>
        </div>
    </form>
@endsection
