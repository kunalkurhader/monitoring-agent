<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;

class EnvironmentFile
{
    public function isWritable(): bool
    {
        return File::exists(base_path('.env')) && is_writable(base_path('.env'));
    }

    public function contents(): string
    {
        return File::get(base_path('.env'));
    }

    /** @param array<string, string|int> $values */
    public function replace(array $values): void
    {
        if (! $this->isWritable()) {
            throw new RuntimeException('The .env file is missing or not writable by the web server.');
        }

        $contents = $this->contents();

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->environmentValue((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';
            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, $line, $contents)
                : rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        $this->write($contents);
    }

    public function write(string $contents): void
    {
        if (File::put(base_path('.env'), $contents, true) === false) {
            throw new RuntimeException('The .env file could not be updated.');
        }
    }

    private function environmentValue(string $value): string
    {
        return '"'.str_replace(
            ['\\', '"', '$', "\r", "\n"],
            ['\\\\', '\\"', '\\$', '', '\\n'],
            $value,
        ).'"';
    }
}
