<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in · Pulsewatch</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="grid min-h-screen place-items-center bg-slate-950 px-6 text-slate-100">
<main class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-8 shadow-2xl">
 @if(session('status'))<div class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-300">{{ session('status') }}</div>@endif
 <div class="mb-7"><p class="text-sm font-medium text-emerald-400">Pulsewatch</p><h1 class="mt-2 text-3xl font-semibold">Welcome back</h1><p class="mt-2 text-sm text-slate-400">Sign in with the administrator account created during setup.</p></div>
 <form method="POST" action="{{ route('login.store') }}" class="space-y-5">@csrf
  <div><label class="setup-label" for="email">Email</label><input class="setup-input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email">@error('email')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror</div>
  <div><label class="setup-label" for="password">Password</label><input class="setup-input" id="password" name="password" type="password" required autocomplete="current-password"></div>
  <label class="flex items-center gap-2 text-sm text-slate-400"><input name="remember" type="checkbox" value="1" class="rounded border-slate-700 bg-slate-950">Remember me</label>
  <button class="w-full rounded-xl bg-emerald-400 px-4 py-3 font-semibold text-slate-950 hover:bg-emerald-300" type="submit">Sign in</button>
 </form>
</main></body></html>
