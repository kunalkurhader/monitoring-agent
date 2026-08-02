<?php

namespace App\Http\Controllers;

use App\Models\AgentApiToken;
use App\Services\AgentJarBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class AgentInstallController extends Controller
{
    public function index(): View
    {
        return view('agents.install');
    }

    public function token(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
        $plainTextToken = Str::random(64);

        AgentApiToken::query()->create([
            'name' => $validated['name'],
            'token_hash' => hash('sha256', $plainTextToken),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'token' => $plainTextToken,
            'message' => 'Token created. It will not be shown again.',
        ], 201);
    }

    public function build(AgentJarBuilder $builder): JsonResponse
    {
        try {
            $result = $builder->build();
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], str_contains($exception->getMessage(), 'already running') ? 409 : 422);
        }

        return response()->json([
            'download_url' => route('agent.download', ['token' => $result['token']]),
            'expires_at' => $result['artifact']->expires_at->toIso8601String(),
            'size_bytes' => $result['artifact']->size_bytes,
            'message' => 'Fresh agent.jar built. This download expires in 10 minutes.',
        ], 201);
    }
}
