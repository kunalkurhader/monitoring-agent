<?php

namespace Tests\Feature;

use App\Models\BrowserProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BrowserMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private const VIEW_ID = 'a61b33c0-42de-4e85-9fe9-7bd9c54618a1';

    public function test_admin_can_create_browser_monitor_and_member_can_view_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)->post(route('browser-monitoring.store'), [
            'name' => 'Customer portal',
            'site_url' => 'https://app.example.com/dashboard?secret=removed',
        ])->assertRedirect(route('settings.index').'#browser-agent');

        $project = BrowserProject::query()->firstOrFail();
        $this->assertSame('https://app.example.com', $project->allowed_origin);
        $this->assertStringStartsWith('pw_', $project->public_key);
        $this->actingAs($member)->get(route('browser-monitoring.index'))
            ->assertOk()->assertSee('Customer portal')->assertDontSee($project->public_key);
        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()->assertSee('Customer portal')->assertSee($project->public_key);
        $this->actingAs($member)->post(route('browser-monitoring.store'), [
            'name' => 'Denied', 'site_url' => 'https://denied.example.com',
        ])->assertForbidden();
    }

    public function test_matching_browser_origin_can_ingest_batched_events(): void
    {
        $project = $this->project();

        $this->call(
            'POST', '/api/v1/browser/events', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_ORIGIN' => 'https://app.example.com'],
            json_encode(['key' => $project->public_key, 'events' => [
                ['view_id' => self::VIEW_ID, 'type' => 'page_load', 'page_url' => 'https://app.example.com/orders?token=sensitive', 'message' => 'reload', 'metrics' => ['load_time' => 840, 'lcp' => 510]],
                ['view_id' => self::VIEW_ID, 'type' => 'ajax', 'page_url' => 'https://app.example.com/orders', 'message' => 'GET', 'source' => 'https://app.example.com/api/orders?page=2', 'metrics' => ['duration' => 240, 'status' => 500]],
                ['view_id' => self::VIEW_ID, 'type' => 'error', 'page_url' => 'https://app.example.com/orders', 'message' => 'Undefined value', 'source' => 'https://app.example.com/app.js?v=1', 'line' => 12],
            ]])
        )->assertAccepted()->assertJsonPath('accepted', 3)
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');

        $this->assertDatabaseHas('browser_events', ['browser_project_id' => $project->id, 'page_view_id' => self::VIEW_ID, 'page_url' => 'https://app.example.com/orders', 'event_type' => 'page_load']);
        $this->assertDatabaseHas('browser_events', ['event_type' => 'ajax', 'source' => 'https://app.example.com/api/orders']);
        $this->assertDatabaseMissing('browser_events', ['page_url' => 'https://app.example.com/orders?token=sensitive']);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('browser-monitoring.index', ['project' => $project->id]))
            ->assertOk()->assertSee('Main reload')->assertSee('AJAX / HTMX')->assertSee('500')->assertSee('Undefined value');

        $this->actingAs($user)->get(route('browser-monitoring.index', [
            'project' => $project->id,
            'from' => now()->subMinute()->format('Y-m-d\TH:i'),
            'to' => now()->addMinute()->format('Y-m-d\TH:i'),
        ]))->assertOk()->assertSee('3 matching events')->assertSee('Main reload');

        $this->actingAs($user)->get(route('browser-monitoring.index', [
            'project' => $project->id,
            'from' => now()->subDays(3)->format('Y-m-d\TH:i'),
            'to' => now()->subDays(2)->format('Y-m-d\TH:i'),
        ]))->assertOk()->assertSee('0 matching events')->assertDontSee('Main reload');
    }

    public function test_ingestion_rejects_wrong_header_origin_before_inserting(): void
    {
        $project = $this->project();

        $this->postEvent($project, 'https://attacker.example', 'https://app.example.com/page')->assertForbidden();
        $this->assertDatabaseCount('browser_events', 0);
    }

    public function test_ingestion_rejects_page_url_from_another_origin(): void
    {
        $project = $this->project();

        $this->postEvent($project, 'https://app.example.com', 'https://attacker.example/page')->assertForbidden();
        $this->assertDatabaseCount('browser_events', 0);
    }

    public function test_ingestion_requires_valid_key_and_origin_header(): void
    {
        $project = $this->project();
        $this->postEvent($project, null, 'https://app.example.com/page')->assertForbidden();

        $project->update(['public_key' => 'pw_replaced']);
        $this->withHeader('Origin', 'https://app.example.com')->postJson('/api/v1/browser/events', [
            'key' => 'pw_unknown', 'events' => [['view_id' => self::VIEW_ID, 'type' => 'error', 'page_url' => 'https://app.example.com/page']],
        ])->assertForbidden();
        $this->assertDatabaseCount('browser_events', 0);
    }

    public function test_main_browser_requests_are_paginated_with_range_filters_preserved(): void
    {
        $project = $this->project();
        $user = User::factory()->create();
        $rows = [];
        for ($index = 0; $index < 25; $index++) {
            $sampledAt = now()->subSeconds($index);
            $rows[] = [
                'browser_project_id' => $project->id,
                'page_view_id' => sprintf('00000000-0000-4000-8000-%012d', $index),
                'event_type' => 'page_load',
                'page_url' => "https://app.example.com/page-{$index}",
                'message' => 'navigate',
                'metrics' => json_encode(['load_time' => 500 + $index]),
                'occurred_at' => $sampledAt,
                'created_at' => $sampledAt,
                'updated_at' => $sampledAt,
            ];
        }
        DB::table('browser_events')->insert($rows);
        $query = [
            'project' => $project->id,
            'from' => now()->subHour()->format('Y-m-d\TH:i'),
            'to' => now()->addMinute()->format('Y-m-d\TH:i'),
        ];

        $this->actingAs($user)->get(route('browser-monitoring.index', $query))
            ->assertOk()->assertSee('page=2', false)->assertSee('page-0');
        $this->actingAs($user)->get(route('browser-monitoring.index', [...$query, 'page' => 2]))
            ->assertOk()->assertSee('page-20');
    }

    private function project(): BrowserProject
    {
        $user = User::factory()->create(['is_admin' => true]);

        return BrowserProject::query()->create([
            'name' => 'App', 'site_url' => 'https://app.example.com', 'allowed_origin' => 'https://app.example.com',
            'public_key' => 'pw_'.str_repeat('a', 60), 'created_by' => $user->id,
        ]);
    }

    private function postEvent(BrowserProject $project, ?string $origin, string $pageUrl): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'text/plain'];
        if ($origin) {
            $server['HTTP_ORIGIN'] = $origin;
        }

        return $this->call('POST', '/api/v1/browser/events', [], [], [], $server, json_encode([
            'key' => $project->public_key,
            'events' => [['view_id' => self::VIEW_ID, 'type' => 'error', 'page_url' => $pageUrl, 'message' => 'Test']],
        ]));
    }
}
