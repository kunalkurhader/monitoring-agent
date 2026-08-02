<!DOCTYPE html>
<html lang="en" class="scheme-light">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Profile · Pulsewatch</title><script>if(localStorage.getItem('pulsewatch-theme')==='dark'){document.documentElement.classList.add('dark');document.documentElement.classList.replace('scheme-light','scheme-dark')}</script>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100"><x-app-header /><main class="mx-auto max-w-2xl px-5 py-6"><h1 class="text-xl font-semibold">Profile</h1>
@if(session('status'))<div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>@endif
<section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"><form method="POST" action="{{ route('profile.update') }}" class="space-y-5">@csrf @method('PATCH')
<div><label class="setup-label" for="name">Name</label><input class="setup-input" id="name" name="name" value="{{ old('name', $user->name) }}" required>@error('name')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror</div>
<div><label class="setup-label">Email</label><input class="setup-input opacity-70" value="{{ $user->email }}" disabled><p class="mt-1 text-xs text-slate-500">Email identifies your team account and cannot be changed here.</p></div>
<div><label class="setup-label">Role</label><p class="rounded-xl bg-slate-100 px-4 py-3 text-sm dark:bg-slate-800">{{ $user->is_admin ? 'Administrator' : 'Member' }}</p></div>
<div class="border-t border-slate-200 pt-5 dark:border-slate-800"><h2 class="font-medium">Change password</h2><p class="mt-1 text-xs text-slate-500">Leave these fields empty to keep your existing password.</p></div>
<div><label class="setup-label" for="password">New password</label><input class="setup-input" id="password" name="password" type="password">@error('password')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror</div>
<div><label class="setup-label" for="password_confirmation">Confirm new password</label><input class="setup-input" id="password_confirmation" name="password_confirmation" type="password"></div>
<button class="rounded-xl bg-emerald-500 px-5 py-3 font-medium text-white hover:bg-emerald-400">Save profile</button></form></section></main></body></html>
