# Production Go-Live & Security Verification Checklist

**ระบบจัดเก็บเอกสารผู้ป่วย แผนกห้องคลอด โรงพยาบาลเอกชล (Aikchol Hospital LRDMS)**

---

## 1. Environment & Secrets
- [ ] `APP_ENV=production` และ `APP_DEBUG=false`
- [ ] สร้าง `APP_KEY` และ `PATIENT_HASH_KEY` ใหม่เฉพาะ Production (ไม่ซ้ำกับ Dev/UAT)
- [ ] ไม่มีการ Commit `.env`, Password, Hostname หรือ IP จริงลงใน Git
- [ ] ลบหรือปิดใช้งานบัญชี Demo (`DatabaseSeeder` ไม่สร้าง Demo users เมื่อ `APP_ENV=production`) และสร้างบัญชี Admin จริงด้วยรหัสผ่านแข็งแกร่ง

## 2. HTTPS, Cookies & Security Headers
- [ ] บังคับใช้งาน HTTPS (TLS 1.2/1.3) และ Redirect HTTP → HTTPS บน Web Server
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_ENCRYPT=true`, `SESSION_LIFETIME=30`
- [ ] ตรวจสอบ Response Headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `Content-Security-Policy`, `Strict-Transport-Security`
- [ ] หน้าที่มีข้อมูลผู้ป่วยและ Preview ส่ง `Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private`

## 3. Dual Database & SQL Server 2014 Security
- [ ] MySQL (`mysql`) และ SQL Server 2014 (`patient_sqlsrv`) แยก Connection และบัญชีผู้ใช้อย่างชัดเจน
- [ ] บัญชี SQL Server 2014 มีสิทธิ์เฉพาะ **Read-Only** (`SELECT`/`EXECUTE` บน View/SP ที่กำหนดเท่านั้น)
- [ ] ตรวจสอบ `PATIENT_DB_ENCRYPT=true` และ `PATIENT_DB_TRUST_SERVER_CERTIFICATE=false` (ติดตั้ง Internal Root CA บน Web Server เรียบร้อย)
- [ ] ทดสอบกรณี SQL Server 2014 ไม่พร้อมใช้งาน (Timeout / Offline) ยืนยันว่าไม่แสดง SQL Error/Stack Trace และระบบเอกสารใน MySQL ยังเปิดดู/ทำงานได้ปกติ

## 4. Private File Storage & Document Governance
- [ ] `DocumentRoot` ชี้ไปที่ `public/` เท่านั้น และยืนยันว่าไม่สามารถเข้าถึง `storage/app/private` ผ่าน URL โดยตรง
- [ ] ทดสอบอัปโหลดไฟล์ PDF จริง, ภาพ JPG/PNG และทดสอบปฏิเสธไฟล์อันตราย (`.exe`, `.php`, `.php.pdf`)
- [ ] ทดสอบหน้า "ตรวจสอบก่อนบันทึก" + PDF.js Preview (Zoom, Rotate, Fit Width, Page Nav, Full Screen) ทั้งบน Desktop, Tablet และ Mobile
- [ ] ทดสอบเพิ่ม Revision ใหม่ ยืนยันว่าไฟล์เก่าไม่ถูกเขียนทับ, Revision เดิมเปลี่ยนเป็น `superseded` และมี `current` เพียง 1 รายการ
- [ ] ตรวจสอบ `DOCUMENT_PERMANENT_DELETE_ENABLED=false` เป็นค่าเริ่มต้น
- [ ] ตั้งค่า Scheduled Task (`php artisan schedule:run`) สำหรับล้าง `tmp_uploads` เรียบร้อย

## 5. RBAC, PDPA & Audit Log
- [ ] ทดสอบสิทธิ์ทั้ง 4 บทบาท (`Viewer`, `Author`, `Supervisor`, `Administrator`) ฝั่งเซิร์ฟเวอร์
- [ ] ยืนยันว่าข้อมูล PII ในตาราง `patients` และ `document_patient_snapshots` ถูกเข้ารหัส (Ciphertext) ใน MySQL
- [ ] ยืนยันว่าไม่มี HN หรือชื่อผู้ป่วยแบบข้อความชัดเจนใน `audit_logs` และ `storage/logs/laravel.log`
- [ ] ทดสอบ Automated Test Suite (`php artisan test`) ผ่านครบทุกกรณี 100%

## 6. Branding & Backup
- [ ] ตรวจสอบโลโก้ Aikchol Hospital บนหน้า Login, Sidebar, Topbar, Review Before Save และ Print Header ว่าคมชัด ไม่บิดเบี้ยว และมี Clear Space เหมาะสม
- [ ] ตั้งค่า Automated Encrypted Backup สำหรับ MySQL + `storage/app/private/documents` + Off-site Copy และบันทึกผลการทดสอบ Restore ครั้งแรกเรียบร้อย
