<?php

return [
    // Browser-monitoring CORS is handled after per-project origin validation
    // in BrowserEventController. Do not enable a wildcard for ingestion.
    'paths' => [],
    'allowed_methods' => [],
    'allowed_origins' => [],
    'allowed_origins_patterns' => [],
    'allowed_headers' => [],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
