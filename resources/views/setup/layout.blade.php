<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scheme-light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>@yield('title') · {{ config('app.name', 'Pulsewatch') }}</title>
        <script>
            if (localStorage.getItem('pulsewatch-theme') === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.classList.replace('scheme-light', 'scheme-dark');
            }
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased transition-colors dark:bg-slate-950 dark:text-slate-100">
        <div class="relative isolate min-h-screen overflow-hidden">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_15%,rgba(16,185,129,.12),transparent_28%),radial-gradient(circle_at_85%_80%,rgba(14,165,233,.08),transparent_30%)]"></div>

            <main class="relative mx-auto grid min-h-screen max-w-7xl items-center gap-12 px-5 py-10 lg:grid-cols-[.8fr_1.2fr] lg:px-10">
                <section class="hidden lg:block">
                    <div class="flex items-center justify-between">
                        <a href="/" class="inline-flex items-center gap-3 text-lg font-semibold tracking-tight">
                            <span class="grid size-10 place-items-center rounded-xl bg-emerald-400 text-slate-950 shadow-lg shadow-emerald-400/20">
                                <span class="size-3 rounded-full border-[3px] border-current"></span>
                            </span>
                            Pulsewatch
                        </a>
                        <button type="button" data-theme-toggle class="rounded-xl border border-slate-200 bg-white/70 px-3 py-2 text-sm font-medium text-slate-600 shadow-sm transition hover:bg-white dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Switch color theme">
                            <span data-theme-light>☾ Dark</span>
                            <span data-theme-dark class="hidden">☀ Light</span>
                        </button>
                    </div>
                    <h1 class="mt-14 max-w-md text-5xl font-semibold leading-[1.08] tracking-[-0.04em]">
                        Your infrastructure, under your control.
                    </h1>
                    <p class="mt-6 max-w-md text-lg leading-8 text-slate-600 dark:text-slate-400">
                        Connect your database and create the first administrator. Your monitoring workspace will be ready in minutes.
                    </p>

                    <div class="mt-12 space-y-5">
                        @foreach ([
                            [1, 'Choose database', 'MySQL, PostgreSQL, or Oracle'],
                            [2, 'Test connection', 'Local, cloud, or managed database'],
                            [3, 'Create administrator', 'Secure your new workspace'],
                        ] as [$number, $label, $description])
                            <div class="flex items-center gap-4">
                                <span class="grid size-9 place-items-center rounded-full border text-sm font-semibold {{ $step >= $number ? 'border-emerald-400 bg-emerald-400 text-slate-950' : 'border-slate-300 text-slate-400 dark:border-slate-700 dark:text-slate-500' }}">
                                    @if ($step > $number) ✓ @else {{ $number }} @endif
                                </span>
                                <div>
                                    <p class="font-medium {{ $step >= $number ? 'text-slate-900 dark:text-white' : 'text-slate-500' }}">{{ $label }}</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-500">{{ $description }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="mx-auto w-full max-w-2xl">
                    <div class="mb-7 flex items-center justify-between lg:hidden">
                        <span class="font-semibold">Pulsewatch</span>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Step {{ $step }} of 3</span>
                            <button type="button" data-theme-toggle class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900" aria-label="Switch color theme">
                                <span data-theme-light>☾</span>
                                <span data-theme-dark class="hidden">☀</span>
                            </button>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200/80 bg-white/85 p-6 shadow-2xl shadow-slate-300/30 backdrop-blur-xl transition-colors dark:border-white/10 dark:bg-slate-900/80 dark:shadow-black/25 sm:p-10">
                        <div class="mb-8 flex items-center gap-2" aria-label="Setup progress">
                            @for ($i = 1; $i <= 3; $i++)
                                <span class="h-1.5 flex-1 rounded-full {{ $step >= $i ? 'bg-emerald-400' : 'bg-slate-200 dark:bg-slate-700' }}"></span>
                            @endfor
                        </div>

                        @if ($errors->any())
                            <div class="mb-6 rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200" role="alert">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        @yield('content')
                    </div>
                    <p class="mt-5 text-center text-xs text-slate-500 dark:text-slate-600">Credentials are encrypted in the setup session and never sent to a third party.</p>
                </section>
            </main>
        </div>
    </body>
</html>
