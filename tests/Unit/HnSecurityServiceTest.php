<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\HnSecurityService;
use Tests\TestCase;

class HnSecurityServiceTest extends TestCase
{
    public function test_normalizes_and_validates_hn_strictly(): void
    {
        $svc = new HnSecurityService();

        $this->assertSame('HN-6601234', $svc->normalize('  hn-6601234  '));
        $this->assertTrue($svc->isValid('66-012345'));
        $this->assertTrue($svc->isValid('00123456'));

        // Reject SQL injection payloads, quotes, semicolons, comments, spaces, too short
        $this->assertFalse($svc->isValid("1234' OR '1'='1"));
        $this->assertFalse($svc->isValid('1234; DROP TABLE patients;'));
        $this->assertFalse($svc->isValid('1234--comment'));
        $this->assertFalse($svc->isValid('12'));
    }

    public function test_masks_thai_national_id_and_sanitizes_log_context(): void
    {
        $svc = new HnSecurityService();

        $this->assertSame('1-2345-XXXXX-23-4', $svc->maskNationalId('1234567890234'));
        $this->assertSame('1-2345-XXXXX-67-8', $svc->maskNationalId('1-2345-99999-67-8'));

        $sanitized = $svc->sanitizeForLogging([
            'hn' => '66-000123',
            'first_name' => 'สมหญิง',
            'last_name' => 'ใจดี',
            'patient_db_password' => 'SuperSecret!123',
            'safe_key' => 'PWD=MyPass123;Uid=sa; failed for 1234567890123',
        ]);

        $this->assertSame('[REDACTED]', $sanitized['hn']);
        $this->assertSame('[REDACTED]', $sanitized['first_name']);
        $this->assertSame('[REDACTED]', $sanitized['last_name']);
        $this->assertSame('[REDACTED]', $sanitized['patient_db_password']);
        $this->assertStringNotContainsString('MyPass123', (string) $sanitized['safe_key']);
        $this->assertStringNotContainsString('1234567890123', (string) $sanitized['safe_key']);
    }
}
