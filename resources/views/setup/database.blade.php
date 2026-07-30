@extends('setup.layout', ['step' => 1])

@section('title', 'Choose your database')

@section('content')
    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-400">Step 1 of 3</p>
    <h2 class="mt-3 text-3xl font-semibold tracking-tight">Choose your database</h2>
    <p class="mt-3 text-slate-600 dark:text-slate-400">Select the database that will store monitoring data and application settings.</p>

    <form method="POST" action="{{ route('setup.database.store') }}" class="mt-8">
        @csrf
        <fieldset class="grid gap-3 sm:grid-cols-3">
            <legend class="sr-only">Database type</legend>
            @foreach ([
                'mysql' => ['MySQL', 'Popular and reliable', 'https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/mysql/mysql-original.svg'],
                'pgsql' => ['PostgreSQL', 'Powerful and extensible', 'https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/postgresql/postgresql-original.svg'],
                'oracle' => ['Oracle', 'Enterprise database', 'https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/oracle/oracle-original.svg'],
            ] as $value => [$label, $description, $logo])
                <label class="group cursor-pointer">
                    <input type="radio" name="driver" value="{{ $value }}" class="peer sr-only" @checked(old('driver', $selected) === $value)>
                    <span class="block h-full rounded-2xl border border-slate-200 bg-slate-50/70 p-5 transition hover:border-slate-400 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-400 dark:border-slate-700 dark:bg-slate-950/40 dark:hover:border-slate-500 dark:peer-checked:border-emerald-400 dark:peer-checked:bg-emerald-400/[.07]">
                        <span class="grid h-12 w-14 place-items-center rounded-xl border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                            <img src="{{ $logo }}" alt="" class="max-h-8 max-w-10" loading="eager">
                        </span>
                        <span class="mt-5 block font-semibold">{{ $label }}</span>
                        <span class="mt-1 block text-sm leading-5 text-slate-500 dark:text-slate-500">{{ $description }}</span>
                    </span>
                </label>
            @endforeach
        </fieldset>

        <div class="mt-8 flex justify-end">
            <button class="rounded-xl bg-emerald-400 px-5 py-3 font-semibold text-slate-950 transition hover:bg-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:ring-offset-2 focus:ring-offset-slate-900">
                Continue <span aria-hidden="true">→</span>
            </button>
        </div>
    </form>
@endsection
