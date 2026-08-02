<?php

namespace App\Http\Controllers;

use App\Models\AgentBuildArtifact;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgentDownloadController extends Controller
{
    public function __invoke(string $token): BinaryFileResponse
    {
        $artifact = AgentBuildArtifact::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($artifact->path), 404);
        $artifact->update(['last_downloaded_at' => now()]);

        return response()->download(Storage::disk('local')->path($artifact->path), 'agent.jar', [
            'Content-Type' => 'application/java-archive',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
