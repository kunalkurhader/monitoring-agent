<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;

class SslCertificateInspector
{
    public function expiresAt(string $url): CarbonImmutable
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?: 443;
        if (! is_string($host) || $host === '') {
            throw new RuntimeException('The monitor URL has no valid host.');
        }

        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'SNI_enabled' => true,
            'peer_name' => $host,
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]]);
        $socket = @stream_socket_client("ssl://{$host}:{$port}", $errorCode, $errorMessage, 10, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new RuntimeException($errorMessage ?: "TLS connection failed ({$errorCode}).");
        }

        $certificate = stream_context_get_params($socket)['options']['ssl']['peer_certificate'] ?? null;
        fclose($socket);
        $parsed = $certificate ? openssl_x509_parse($certificate) : false;
        if (! is_array($parsed) || ! isset($parsed['validTo_time_t'])) {
            throw new RuntimeException('The remote certificate expiry could not be read.');
        }

        return CarbonImmutable::createFromTimestampUTC((int) $parsed['validTo_time_t']);
    }
}
