<!DOCTYPE html>
<html lang="en" class="scheme-light">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Install Agent · {{ config('app.name', 'Monitoring Agent') }}</title>
    <script>if(localStorage.getItem('monitoring-agent-theme')==='dark'){document.documentElement.classList.add('dark');document.documentElement.classList.replace('scheme-light','scheme-dark')}</script>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <x-app-header active="install" />
    <main class="mx-auto max-w-screen-2xl px-4 py-5 sm:px-6">
        <div class="mb-5"><h1 class="text-xl font-semibold">Install monitoring agent</h1><p class="mt-1 text-sm text-slate-500">Generate a token and copy one command to a Linux server.</p></div>
        @include('dashboard.install-agent')
    </main>
</body>
</html>
