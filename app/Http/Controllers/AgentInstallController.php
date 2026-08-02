<?php

namespace App\Http\Controllers;

use App\Models\AgentApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

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
}
