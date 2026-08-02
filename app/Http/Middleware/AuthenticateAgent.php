<?php

namespace App\Http\Middleware;

use App\Models\AgentApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = (string) config('services.agent.token');
        $providedToken = (string) $request->bearerToken();

        if ($providedToken === '') {
            return new JsonResponse(['message' => 'Invalid agent API token.'], 401);
        }

        $legacyTokenIsValid = $configuredToken !== '' && hash_equals($configuredToken, $providedToken);
        $token = $legacyTokenIsValid ? null : AgentApiToken::query()
            ->where('token_hash', hash('sha256', $providedToken))
            ->whereNull('revoked_at')
            ->first();

        if (! $legacyTokenIsValid && ! $token) {
            return new JsonResponse(['message' => 'Invalid agent API token.'], 401);
        }

        if ($token && (! $token->last_used_at || $token->last_used_at->lt(now()->subMinutes(5)))) {
            $token->update(['last_used_at' => now()]);
        }

        return $next($request);
    }
}
