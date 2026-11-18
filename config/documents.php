<?php

return [
    // Allowed file extensions and strictly matched real MIME types
    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'],

    'allowed_mimes' => [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ],
    ],

    // Maximum upload file size in kilobytes (default: 20 MB)
    'max_file_size_kb' => (int) env('DOCUMENT_MAX_FILE_SIZE_KB', 20480),

    // Short-lived signed URL lifetime for PDF.js preview streaming (minutes)
    'preview_url_ttl_minutes' => (int) env('DOCUMENT_PREVIEW_URL_TTL', 10),

    // Temporary staged upload retention before scheduled purge (hours)
    'tmp_upload_retention_hours' => (int) env('DOCUMENT_TMP_RETENTION_HOURS', 24),

    // Permanent delete policy (strictly disabled by default)
    'permanent_delete_enabled' => filter_var(env('DOCUMENT_PERMANENT_DELETE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
];
