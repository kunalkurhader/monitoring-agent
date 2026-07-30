<?php

namespace App\Http\Middleware;

use App\Http\Controllers\SetupController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationIsNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (SetupController::installed()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
