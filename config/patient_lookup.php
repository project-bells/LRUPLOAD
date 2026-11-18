<?php

return [
    'enabled' => filter_var(env('PATIENT_DB_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'connection' => 'patient_sqlsrv',
    'source_system' => env('PATIENT_SOURCE_SYSTEM', 'his_sqlsrv_2014'),

    // Configurable HN validation pattern (adjustable once Aikchol Hospital DBA confirms exact format)
    'hn_regex' => env('PATIENT_HN_REGEX', '/^[A-Za-z0-9\-]{4,20}$/'),
    'hn_min_length' => (int) env('PATIENT_HN_MIN_LENGTH', 4),
    'hn_max_length' => (int) env('PATIENT_HN_MAX_LENGTH', 20),

    // HMAC key dedicated to deterministic hn_hash generation (falls back to APP_KEY if unset)
    'hash_key' => env('PATIENT_HASH_KEY', env('APP_KEY')),

    // Rate limiting for HN lookup per user and per IP
    'rate_limit_per_user_per_minute' => (int) env('PATIENT_LOOKUP_RATE_LIMIT_USER', 30),
    'rate_limit_per_ip_per_minute' => (int) env('PATIENT_LOOKUP_RATE_LIMIT_IP', 60),

    // Optional short-lived encrypted server cache (disabled by default for freshness & PDPA)
    'cache_enabled' => filter_var(env('PATIENT_LOOKUP_CACHE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'cache_ttl_seconds' => (int) env('PATIENT_LOOKUP_CACHE_TTL', 30),

    // Timeouts
    'login_timeout' => (int) env('PATIENT_DB_LOGIN_TIMEOUT', 5),
    'query_timeout' => (int) env('PATIENT_DB_QUERY_TIMEOUT', 10),
];
