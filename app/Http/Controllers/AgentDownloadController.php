<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgentDownloadController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $path = base_path('agent/target/agent-1.0.0.jar');

        abort_unless(is_file($path), 503, 'The agent JAR is not built. Run: mvn -f agent/pom.xml clean package');

        return response()->download($path, 'agent.jar', [
            'Content-Type' => 'application/java-archive',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
