<?php

namespace App\Http\Controllers;

use App\Models\BrowserProject;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BrowserMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'project' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'to' => ['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:from'],
        ]);
        $from = isset($validated['from']) ? Carbon::createFromFormat('Y-m-d\TH:i', $validated['from']) : now()->subDay();
        $to = isset($validated['to']) ? Carbon::createFromFormat('Y-m-d\TH:i', $validated['to']) : now();
        $fromInput = $from->format('Y-m-d\TH:i');
        $toInput = $to->format('Y-m-d\TH:i');
        $rangeLabel = $from->format('M j, Y H:i').' – '.$to->format('M j, Y H:i');
        $projects = BrowserProject::query()->orderBy('name')->get();
        $project = $projects->firstWhere('id', (int) ($validated['project'] ?? 0)) ?? $projects->first();
        $eventQuery = $project?->events()->whereBetween('occurred_at', [$from, $to]);
        $matchingEventCount = $eventQuery ? (clone $eventQuery)->count() : 0;
        $pageLoadCount = $eventQuery ? (clone $eventQuery)->whereIn('event_type', ['page_load', 'performance'])->count() : 0;
        $asyncRequestCount = $eventQuery ? (clone $eventQuery)->whereIn('event_type', ['ajax', 'htmx'])->count() : 0;
        $browserErrorCount = $eventQuery ? (clone $eventQuery)->whereIn('event_type', ['error', 'unhandled_rejection', 'resource_error'])->count() : 0;
        $pageLoads = $eventQuery
            ? (clone $eventQuery)->whereIn('event_type', ['page_load', 'performance'])->latest('occurred_at')->paginate(20)->withQueryString()
            : null;
        $visibleViewIds = $pageLoads ? $pageLoads->getCollection()->pluck('page_view_id')->filter()->unique() : collect();
        $linkedEvents = $eventQuery && $visibleViewIds->isNotEmpty()
            ? (clone $eventQuery)->whereIn('page_view_id', $visibleViewIds)->get()
            : collect();

        $asyncDuration = 0.0;
        $asyncDurationSamples = 0;
        $failedRequestCount = 0;
        if ($eventQuery) {
            foreach ((clone $eventQuery)->whereIn('event_type', ['ajax', 'htmx'])->select('metrics')->cursor() as $event) {
                $duration = $event->metrics['duration'] ?? null;
                $status = $event->metrics['status'] ?? 0;
                if (is_numeric($duration)) {
                    $asyncDuration += (float) $duration;
                    $asyncDurationSamples++;
                }
                if ($status === 0 || $status >= 400) {
                    $failedRequestCount++;
                }
            }
        }
        $averageAsync = $asyncDurationSamples ? $asyncDuration / $asyncDurationSamples : null;

        $loadTotal = 0.0;
        $loadSamples = 0;
        $pageTotals = [];
        if ($eventQuery) {
            foreach ((clone $eventQuery)->whereIn('event_type', ['page_load', 'performance'])->select(['page_url', 'metrics'])->cursor() as $event) {
                $loadTime = $event->metrics['load_time'] ?? null;
                if (! is_numeric($loadTime)) {
                    continue;
                }
                $loadTotal += (float) $loadTime;
                $loadSamples++;
                $pageTotals[$event->page_url] ??= ['total' => 0.0, 'samples' => 0];
                $pageTotals[$event->page_url]['total'] += (float) $loadTime;
                $pageTotals[$event->page_url]['samples']++;
            }
        }
        $averageLoad = $loadSamples ? $loadTotal / $loadSamples : null;
        $slowPages = collect($pageTotals)->map(fn (array $row, string $url): array => [
            'url' => $url, 'average' => round($row['total'] / $row['samples']), 'samples' => $row['samples'],
        ])->sortByDesc('average')->take(10)->values();
        $pageViews = ($pageLoads?->getCollection() ?? collect())->map(function ($main) use ($linkedEvents): array {
            $linked = $linkedEvents->where('page_view_id', $main->page_view_id);
            $requests = $linked->whereIn('event_type', ['ajax', 'htmx'])->values();
            $errors = $linked->whereIn('event_type', ['error', 'unhandled_rejection', 'resource_error'])->values();

            return [
                'main' => $main,
                'requests' => $requests,
                'errors' => $errors,
                'failed_requests' => $requests->filter(fn ($event): bool => ($event->metrics['status'] ?? 0) === 0 || ($event->metrics['status'] ?? 0) >= 400)->count(),
            ];
        });

        return view('browser-monitoring.index', compact('projects', 'project', 'matchingEventCount', 'pageLoadCount', 'asyncRequestCount', 'browserErrorCount', 'failedRequestCount', 'averageLoad', 'averageAsync', 'slowPages', 'pageViews', 'pageLoads', 'fromInput', 'toInput', 'rangeLabel'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'site_url' => ['required', 'url:http,https', 'max:2048'],
        ]);
        $origin = $this->origin($validated['site_url']);

        BrowserProject::query()->create([
            ...$validated,
            'allowed_origin' => $origin,
            'public_key' => 'pw_'.Str::random(60),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->to(route('settings.index').'#browser-agent')->with('status', 'Browser monitor created.');
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return strtolower($parts['scheme'].'://'.$parts['host'].$port);
    }
}
