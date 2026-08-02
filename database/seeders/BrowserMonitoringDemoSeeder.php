<?php

namespace Database\Seeders;

use App\Models\BrowserProject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrowserMonitoringDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('is_admin', true)->first() ?? User::factory()->create([
            'name' => 'Demo Administrator', 'email' => 'demo@monitoring-agent.local', 'password' => 'MonitoringAgentDemo123!', 'is_admin' => true,
        ]);
        $project = BrowserProject::query()->updateOrCreate(
            ['public_key' => 'pw_demo_'.str_repeat('b', 52)],
            ['name' => 'Demo Customer Portal', 'site_url' => 'https://portal.demo.test', 'allowed_origin' => 'https://portal.demo.test', 'created_by' => $user->id, 'is_active' => true]
        );
        $project->events()->delete();

        $pages = ['/dashboard', '/orders', '/customers', '/reports/revenue', '/settings/team'];
        $endpoints = ['/api/summary', '/api/orders', '/api/customers/search', '/api/notifications', '/fragments/activity'];
        $rows = [];
        for ($index = 0; $index < 24; $index++) {
            $viewId = (string) Str::uuid();
            $occurredAt = now()->subMinutes((23 - $index) * 47 + 8);
            $page = $pages[$index % count($pages)];
            $loadTime = 620 + (($index * 173) % 2100);
            $navigationType = $index % 5 === 0 ? 'reload' : 'navigate';
            $rows[] = $this->event($project->id, $viewId, 'page_load', 'https://portal.demo.test'.$page, $navigationType, null, [
                'load_time' => $loadTime, 'ttfb' => 120 + ($index * 29 % 480), 'dns' => 14 + $index % 35, 'connect' => 25 + $index % 60,
                'dom_interactive' => (int) ($loadTime * .72), 'lcp' => (int) ($loadTime * .82), 'cls' => round(($index % 7) * .018, 3), 'inp' => 70 + ($index * 19 % 260),
            ], $occurredAt);

            $requestCount = 2 + ($index % 6);
            for ($request = 0; $request < $requestCount; $request++) {
                $isHtmx = $request === $requestCount - 1 && $index % 3 === 0;
                $isFailure = ($index + $request) % 11 === 0;
                $status = $isFailure ? ($index % 2 ? 500 : 0) : ($request % 4 === 0 ? 201 : 200);
                $rows[] = $this->event($project->id, $viewId, $isHtmx ? 'htmx' : 'ajax', 'https://portal.demo.test'.$page, $request % 3 === 0 ? 'POST' : 'GET', 'https://portal.demo.test'.$endpoints[$request % count($endpoints)], [
                    'duration' => 85 + (($index * 71 + $request * 113) % 1300), 'status' => $status,
                ], $occurredAt->copy()->addSeconds(2 + $request * 3));
            }

            if ($index % 4 === 1) {
                $rows[] = $this->event($project->id, $viewId, 'error', 'https://portal.demo.test'.$page, 'Cannot read properties of undefined', 'https://portal.demo.test/assets/app.js', null, $occurredAt->copy()->addSeconds(7), 184, 22);
            }
            if ($index % 9 === 3) {
                $rows[] = $this->event($project->id, $viewId, 'unhandled_rejection', 'https://portal.demo.test'.$page, 'Request timeout while loading notifications', null, null, $occurredAt->copy()->addSeconds(11));
            }
        }

        DB::table('browser_events')->insert($rows);
    }

    private function event(int $projectId, string $viewId, string $type, string $pageUrl, ?string $message, ?string $source, ?array $metrics, $occurredAt, ?int $line = null, ?int $column = null): array
    {
        return ['browser_project_id' => $projectId, 'page_view_id' => $viewId, 'event_type' => $type, 'page_url' => $pageUrl, 'message' => $message, 'source' => $source,
            'line_number' => $line, 'column_number' => $column, 'metrics' => $metrics ? json_encode($metrics) : null, 'user_agent' => 'Monitoring Agent demo browser / Chrome',
            'occurred_at' => $occurredAt, 'created_at' => $occurredAt, 'updated_at' => $occurredAt];
    }
}
