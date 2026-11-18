<?php

declare(strict_types=1);

namespace App\Services;

class HnSecurityService
{
    /**
     * Normalize HN by trimming whitespace and uppercasing ASCII characters.
     */
    public function normalize(string $hn): string
    {
        return strtoupper(trim($hn));
    }

    /**
     * Validate HN against hospital format and reject SQL metacharacters or control bytes.
     */
    public function isValid(string $hn): bool
    {
        $normalized = $this->normalize($hn);
        $min = (int) config('patient_lookup.hn_min_length', 4);
        $max = (int) config('patient_lookup.hn_max_length', 20);
        $length = strlen($normalized);

        if ($length < $min || $length > $max) {
            return false;
        }

        // Reject any SQL injection tokens, quotes, comments, or whitespace
        if (preg_match('/[\s\'"`;\\\\]|--|\/\*|\*\//', $normalized) === 1) {
            return false;
        }

        $regex = (string) config('patient_lookup.hn_regex', '/^[A-Za-z0-9\-]{4,20}$/');

        return preg_match($regex, $normalized) === 1;
    }

    /**
     * Deterministic keyed HMAC-SHA256 hash of HN for indexing, deduplication, and PDPA-safe audit logs.
     */
    public function hash(string $hn): string
    {
        $normalized = $this->normalize($hn);
        $key = (string) config('patient_lookup.hash_key', (string) config('app.key', 'aikchol-default-key'));

        return hash_hmac('sha256', $normalized, $key);
    }

    /**
     * Mask Thai National ID (e.g. 1234567890123 -> 1-2345-XXXXX-23-3 -> 1-2345-XXXXX-12-3).
     */
    public function maskNationalId(?string $nationalId): ?string
    {
        if ($nationalId === null || trim($nationalId) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $nationalId) ?? '';

        if (strlen($digits) === 13) {
            return sprintf(
                '%s-%s-XXXXX-%s-%s',
                substr($digits, 0, 1),
                substr($digits, 1, 4),
                substr($digits, 10, 2),
                substr($digits, 12, 1)
            );
        }

        if (strlen($digits) >= 6) {
            return substr($digits, 0, 2) . str_repeat('X', strlen($digits) - 4) . substr($digits, -2);
        }

        return 'XXXXXXXXXXXXX';
    }

    /**
     * Recursively scrub sensitive PII & secret fields before writing to logs or audit JSON columns.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sanitizeForLogging(array $data): array
    {
        $blockedKeys = [
            'hn',
            'mother_hn',
            'child_hn',
            'first_name',
            'last_name',
            'full_name',
            'patient_name',
            'display_name',
            'title_name',
            'birth_date',
            'national_id',
            'cid',
            'id_card',
            'password',
            'password_confirmation',
            'patient_db_password',
            'secret',
            'token',
            'connection_string',
            'dsn',
            'sql',
            'file_content',
        ];

        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            $isBlocked = false;
            foreach ($blockedKeys as $blocked) {
                if ($lowerKey === $blocked || str_contains($lowerKey, 'password') || str_contains($lowerKey, 'secret')) {
                    $isBlocked = true;
                    break;
                }
            }

            if ($isBlocked) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeForLogging($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->redactInlineSecrets($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Redact potential connection credentials or 13-digit national IDs from exception messages.
     */
    public function redactInlineSecrets(string $text): string
    {
        // Mask PWD=...; or Password=...; in ODBC strings
        $text = (string) preg_replace('/(PWD|Password|Uid|User Id)\s*=\s*[^;]+/i', '$1=[REDACTED]', $text);
        // Mask 13-digit Thai national ID numbers
        $text = (string) preg_replace('/\b\d{13}\b/', '[REDACTED_ID]', $text);

        return $text;
    }
}
