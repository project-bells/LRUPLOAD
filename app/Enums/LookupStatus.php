<?php

declare(strict_types=1);

namespace App\Enums;

enum LookupStatus: string
{
    case Found = 'found';
    case NotFound = 'not_found';
    case InvalidHn = 'invalid_hn';
    case Timeout = 'timeout';
    case ConnectionFailed = 'connection_failed';
    case AccessDenied = 'access_denied';
    case MappingError = 'mapping_error';
    case UnexpectedError = 'unexpected_error';

    public function userMessageTh(): string
    {
        return match ($this) {
            self::Found => 'พบข้อมูลผู้ป่วย',
            self::NotFound => 'ไม่พบข้อมูลผู้ป่วยจาก HN ที่ระบุ',
            self::InvalidHn => 'รูปแบบ HN ไม่ถูกต้อง กรุณาตรวจสอบและลองใหม่อีกครั้ง',
            self::Timeout => 'ระบบข้อมูลผู้ป่วยตอบสนองล่าช้าเกินกำหนด กรุณาลองใหม่ในภายหลัง',
            self::ConnectionFailed => 'ไม่สามารถเชื่อมต่อระบบข้อมูลผู้ป่วยได้ในขณะนี้ กรุณาลองใหม่',
            self::AccessDenied => 'คุณไม่มีสิทธิ์ค้นหาข้อมูลผู้ป่วย',
            self::MappingError => 'ไม่สามารถอ่านโครงสร้างข้อมูลผู้ป่วยจากระบบต้นทางได้ กรุณาติดต่อผู้ดูแลระบบ',
            self::UnexpectedError => 'เกิดข้อผิดพลาดระหว่างค้นหาข้อมูลผู้ป่วย กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ',
        };
    }
}
