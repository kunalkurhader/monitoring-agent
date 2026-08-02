<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BrowserProject;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class BrowserEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (strlen($request->getContent()) > 262144) {
            return response()->json(['message' => 'Browser event payload is too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $payload = json_decode($request->getContent(), true);
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'key' => ['required', 'string', 'max:80'],
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*.view_id' => ['required', 'uuid'],
            'events.*.type' => ['required', 'in:page_load,ajax,htmx,error,unhandled_rejection,resource_error'],
            'events.*.page_url' => ['required', 'url:http,https', 'max:4096'],
            'events.*.message' => ['nullable', 'string', 'max:4000'],
            'events.*.source' => ['nullable', 'url:http,https', 'max:4096'],
            'events.*.line' => ['nullable', 'integer', 'min:0'],
            'events.*.column' => ['nullable', 'integer', 'min:0'],
            'events.*.metrics' => ['nullable', 'array', 'max:20'],
            'events.*.metrics.*' => ['nullable', 'numeric'],
            'events.*.occurred_at' => ['nullable', 'date'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid browser event payload.', 'errors' => $validator->errors()], 422);
        }

        $project = BrowserProject::query()->where('public_key', $payload['key'])->where('is_active', true)->first();
        if (! $project || ! $this->requestMatchesProject($request, $project, $payload['events'])) {
            return response()->json(['message' => 'The request origin is not authorized.'], Response::HTTP_FORBIDDEN);
        }

        $now = now();
        $rows = collect($payload['events'])->map(fn (array $event): array => [
            'browser_project_id' => $project->id,
            'page_view_id' => $event['view_id'],
            'event_type' => $event['type'],
            'page_url' => $this->cleanUrl($event['page_url']),
            'message' => $event['message'] ?? null,
            'source' => isset($event['source']) ? $this->cleanUrl($event['source']) : null,
            'line_number' => $event['line'] ?? null,
            'column_number' => $event['column'] ?? null,
            'metrics' => isset($event['metrics']) ? json_encode($event['metrics']) : null,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            'occurred_at' => isset($event['occurred_at']) ? Carbon::parse($event['occurred_at']) : $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        $project->events()->insert($rows);

        return response()->json(['accepted' => count($rows)], 202)->withHeaders([
            'Access-Control-Allow-Origin' => $project->allowed_origin,
            'Vary' => 'Origin',
        ]);
    }

    public function options(Request $request): Response
    {
        $origin = $request->headers->get('Origin');
        $project = BrowserProject::query()->where('allowed_origin', $this->normalizeOrigin($origin))->where('is_active', true)->exists();
        if (! $origin || ! $project) {
            return response('', 403);
        }

        return response('', 204)->withHeaders([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
            'Access-Control-Max-Age' => '86400',
            'Vary' => 'Origin',
        ]);
    }

    private function requestMatchesProject(Request $request, BrowserProject $project, array $events): bool
    {
        $headerOrigin = $request->headers->get('Origin');
        if (! $headerOrigin && $request->headers->get('Referer')) {
            $headerOrigin = $request->headers->get('Referer');
        }
        if ($this->normalizeOrigin($headerOrigin) !== $project->allowed_origin) {
            return false;
        }

        return collect($events)->every(fn (array $event): bool => $this->normalizeOrigin($event['page_url']) === $project->allowed_origin);
    }

    private function normalizeOrigin(?string $url): ?string
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return strtolower($parts['scheme'].'://'.$parts['host'].$port);
    }

    private function cleanUrl(string $url): string
    {
        $parts = parse_url($url);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port.($parts['path'] ?? '/');
    }
}
