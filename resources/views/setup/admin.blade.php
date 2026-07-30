@extends('setup.layout', ['step' => 3])

@section('title', 'Create administrator')

@section('content')
    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-400">Step 3 of 3</p>
    <h2 class="mt-3 text-3xl font-semibold tracking-tight">Create your administrator</h2>
    <p class="mt-3 text-slate-600 dark:text-slate-400">This account will have full access to your monitoring workspace.</p>

    <form method="POST" action="{{ route('setup.finish') }}" class="mt-8 space-y-5">
        @csrf
        <label class="block">
            <span class="setup-label">Email address</span>
            <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="admin@example.com" required class="setup-input">
        </label>
        <label class="block">
            <span class="setup-label">Password</span>
            <input type="password" name="password" autocomplete="new-password" minlength="12" required class="setup-input">
            <span class="mt-2 block text-xs text-slate-500">Use at least 12 characters with letters and numbers.</span>
        </label>
        <label class="block">
            <span class="setup-label">Retype password</span>
            <input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required class="setup-input">
        </label>

        <div class="flex items-center justify-between pt-3">
            <a href="{{ route('setup.connection') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">← Back</a>
            <button class="rounded-xl bg-emerald-400 px-5 py-3 font-semibold text-slate-950 transition hover:bg-emerald-300">
                Complete setup
            </button>
        </div>
    </form>
@endsection
