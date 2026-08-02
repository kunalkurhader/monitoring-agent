<?php

namespace App\Services;

use App\Models\AgentBuildArtifact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class AgentJarBuilder
{
    public function build(): array
    {
        $lock = Cache::lock('agent-jar-build', 600);
        if (! $lock->get()) {
            throw new RuntimeException('An agent build is already running. Try again shortly.');
        }

        try {
            $this->deleteExpired();
            $maven = (new ExecutableFinder)->find('mvn');
            if (! $maven) {
                throw new RuntimeException('Maven is not installed on the application server.');
            }

            $process = new Process([$maven, '-f', base_path('agent/pom.xml'), 'clean', 'package', '-DskipTests']);
            $process->setTimeout(300);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Agent build failed: '.Str::limit(trim($process->getErrorOutput() ?: $process->getOutput()), 1000));
            }

            $builtJar = collect(File::glob(base_path('agent/target/agent-*.jar')))
                ->reject(fn (string $path): bool => str_starts_with(basename($path), 'original-') || str_contains($path, '-sources.jar') || str_contains($path, '-javadoc.jar'))
                ->sortByDesc(fn (string $path): int => filemtime($path) ?: 0)
                ->first();
            if (! is_string($builtJar) || ! is_file($builtJar)) {
                throw new RuntimeException('Maven completed without producing an agent JAR.');
            }

            $plainToken = Str::random(64);
            $path = 'agent-builds/'.Str::uuid().'.jar';
            Storage::disk('local')->put($path, File::get($builtJar));
            File::delete($builtJar);
            $artifact = AgentBuildArtifact::query()->create([
                'token_hash' => hash('sha256', $plainToken),
                'path' => $path,
                'size_bytes' => Storage::disk('local')->size($path),
                'expires_at' => now()->addMinutes(10),
            ]);

            return ['artifact' => $artifact, 'token' => $plainToken];
        } finally {
            $lock->release();
        }
    }

    public function deleteExpired(): int
    {
        if (! Schema::hasTable('agent_build_artifacts')) {
            return 0;
        }

        $deleted = 0;
        AgentBuildArtifact::query()->where('expires_at', '<=', now())->each(function (AgentBuildArtifact $artifact) use (&$deleted): void {
            Storage::disk('local')->delete($artifact->path);
            $artifact->delete();
            $deleted++;
        });

        return $deleted;
    }
}
