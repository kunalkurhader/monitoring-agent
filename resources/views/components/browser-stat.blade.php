@props(['label', 'value', 'color' => 'emerald'])
@php($colors = ['emerald' => 'text-emerald-600', 'sky' => 'text-sky-600', 'violet' => 'text-violet-600', 'amber' => 'text-amber-600', 'red' => 'text-red-500'])
<div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-semibold {{ $colors[$color] ?? $colors['emerald'] }}">{{ $value }}</p></div>
