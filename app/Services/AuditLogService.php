<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogService
{
    public function __construct(
        private readonly HnSecurityService $hnSecurity,
    ) {
    }

    /**
     * Append a strictly immutable audit log record. Never stores plaintext HN, patient names, or secrets.
     *
     * @param array<string, mixed>|null $beforeData
     * @param array<string, mixed>|null $afterData
     */
    public function record(
        string $action,
        ?User $user = null,
        string $outcome = 'success',
        ?int $documentId = null,
        ?int $revisionId = null,
        ?string $hnHash = null,
        ?array $beforeData = null,
        ?array $afterData = null,
        ?Request $request = null,
        ?int $departmentId = null,
    ): AuditLog {
        $req = $request ?? (app()->bound('request') ? app('request') : null);
        $actor = $user ?? ($req?->user() instanceof User ? $req->user() : null);

        $requestId = null;
        if ($req instanceof Request) {
            $attr = $req->attributes->get('request_id');
            $requestId = is_string($attr) && $attr !== '' ? $attr : (string) Str::uuid();
        } else {
            $requestId = (string) Str::uuid();
        }

        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'department_id' => $departmentId ?? $actor?->department_id,
            'action' => $action,
            'document_id' => $documentId,
            'revision_id' => $revisionId,
            'hn_hash' => $hnHash,
            'outcome' => $outcome,
            'ip_address' => $req instanceof Request ? $req->ip() : null,
            'user_agent' => $req instanceof Request ? Str::limit((string) $req->userAgent(), 490, '') : null,
            'request_id' => $requestId,
            'before_data' => $beforeData !== null ? $this->hnSecurity->sanitizeForLogging($beforeData) : null,
            'after_data' => $afterData !== null ? $this->hnSecurity->sanitizeForLogging($afterData) : null,
            'created_at' => now(),
        ]);
    }
}
