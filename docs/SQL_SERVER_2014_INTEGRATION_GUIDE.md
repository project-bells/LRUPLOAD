# คู่มือการเชื่อมต่อ Microsoft SQL Server 2014 (Read-Only)

**ระบบจัดเก็บเอกสารผู้ป่วย แผนกห้องคลอด โรงพยาบาลเอกชล**

---

## 1. สถาปัตยกรรมการเชื่อมต่อ

- Connection ชื่อ **`patient_sqlsrv`** แยกออกจาก MySQL (`mysql`) อย่างเด็ดขาดใน `config/database.php`
- บราวเซอร์ **ห้าม** เชื่อมต่อ SQL Server โดยตรง ทุกคำขอต้องผ่าน `POST /patients/lookup` (CSRF) → `PatientLookupController` → `PatientLookupService` → `PatientLookupInterface` → `SqlServer2014PatientLookup`
- ใช้ **Read-Only Account** และ **Parameter Binding (`?`)** 100% เท่านั้น

---

## 2. การตรวจสอบความเข้ากันได้ของ Driver และ TLS สำหรับ SQL Server 2014

ก่อนติดตั้ง Driver บนเซิร์ฟเวอร์จริง ต้องตรวจสอบข้อมูลต่อไปนี้ร่วมกับ DBA ของโรงพยาบาลเอกชล:

1. **PHP Driver (`sqlsrv` & `pdo_sqlsrv`):**
   - **Windows Server (PHP 8.2 ZTS x64 เช่น XAMPP):** ดาวน์โหลด Microsoft Drivers for PHP for SQL Server (`php_pdo_sqlsrv_82_ts_x64.dll` และ `php_sqlsrv_82_ts_x64.dll`) จาก Microsoft Official Release และเพิ่ม `extension=pdo_sqlsrv` ใน `php.ini`
   - **Linux (Ubuntu/RHEL + PHP 8.2):** ติดตั้งผ่าน PECL (`pecl install sqlsrv pdo_sqlsrv`) และ `msodbcsql17` หรือ `msodbcsql18`
2. **Microsoft ODBC Driver (17 vs 18) และนโยบาย TLS ของ SQL Server 2014:**
   - **SQL Server 2014 Service Pack:** ต้องตรวจสอบว่าเป็น SP2/SP3 ที่มีอัปเดตรองรับ **TLS 1.2** แล้วหรือไม่
   - **ค่าเริ่มต้นที่ปลอดภัยในระบบนี้:**
     ```env
     PATIENT_DB_ENCRYPT=true
     PATIENT_DB_TRUST_SERVER_CERTIFICATE=false
     PATIENT_DB_LOGIN_TIMEOUT=5
     PATIENT_DB_QUERY_TIMEOUT=10
     ```
   - **กรณีโรงพยาบาลใช้ Internal CA Certificate:** ให้นำ Root CA Certificate ของโรงพยาบาลมาติดตั้งใน Trust Store ของระบบปฏิบัติการ Web Server (Windows Trusted Root CA หรือ `/usr/local/share/ca-certificates/` บน Linux) เพื่อให้ยืนยัน Certificate ผ่านโดย **ไม่ต้องปิด** `PATIENT_DB_TRUST_SERVER_CERTIFICATE`
   - **ข้อควรระวัง:** ห้ามเปลี่ยน `PATIENT_DB_TRUST_SERVER_CERTIFICATE=true` หรือ `PATIENT_DB_ENCRYPT=false` โดยไม่มีบันทึกการประเมินความเสี่ยงและการอนุมัติเป็นลายลักษณ์อักษรจากฝ่ายความปลอดภัยสารสนเทศของโรงพยาบาล (เช่น กรณีเครือข่ายแยก VLAN เฉพาะและ SQL Server 2014 มีข้อจำกัด Certificate ระยะเปลี่ยนผ่าน)

---

## 3. แนวทางแนะนำสำหรับ DBA: สร้าง Read-Only View หรือ Stored Procedure

เพื่อลดความเสี่ยงและไม่เปิดสิทธิ์อ่านตารางฐานข้อมูล HIS ทั้งหมด แนะนำให้ DBA สร้าง Read-Only View หรือ Stored Procedure เฉพาะสำหรับระบบห้องคลอด และมอบสิทธิ์เฉพาะบัญชี `lrdms_his_ro` ดังนี้:

```sql
-- ตัวอย่างสิทธิ์ Read-Only ที่แนะนำ (ปรับชื่อ Schema/View ตามจริง)
CREATE LOGIN lrdms_his_ro WITH PASSWORD = '<STRONG_PASSWORD>';
USE [HIS_DATABASE];
CREATE USER lrdms_his_ro FOR LOGIN lrdms_his_ro;
GRANT SELECT ON dbo.vw_lrd_patient_lookup TO lrdms_his_ro;
-- หรือหากใช้ Stored Procedure:
-- GRANT EXECUTE ON dbo.FindPatientByHn TO lrdms_his_ro;
```

---

## 4. ขั้นตอนการใส่ Query จริงใน `SqlServer2014PatientLookup.php`

เมื่อได้รับชื่อ View/Table/Stored Procedure และชื่อคอลัมน์จริงจากทีม IT โรงพยาบาล ให้แก้ไขเฉพาะไฟล์เดียวคือ:
`app/Integrations/SqlServer2014PatientLookup.php` (ไม่ต้องแก้ Controller, Service หรือหน้าจอ Blade)

1. แทนที่ส่วน Placeholder ในเมธอด `findByHn(string $hn)` ด้วย Parameterized Query:
   ```php
   $row = DB::connection($connectionName)->selectOne(
       'SELECT TOP 1 hn, patient_type, title_name, first_name, last_name, birth_date, sex, national_id, admission_number, ward, mother_hn, child_hn, source_updated_at
        FROM dbo.vw_lrd_patient_lookup
        WHERE hn = ?',
       [$hn]
   );
   ```
   หรือแบบ Stored Procedure:
   ```php
   $row = DB::connection($connectionName)->selectOne(
       'EXEC dbo.FindPatientByHn @hn = ?',
       [$hn]
   );
   ```
2. ปรับอาร์เรย์ `$map` ในเมธอด `mapRowToDto(array $row)` ให้ตรงกับชื่อคอลัมน์จริงของ SQL Server 2014
3. ตรวจสอบรูปแบบ HN ที่ได้จากโรงพยาบาลและอัปเดต `PATIENT_HN_REGEX` ใน `.env`
4. ตั้งค่า `PATIENT_DB_ENABLED=true` ใน `.env` และทดสอบในสภาพแวดล้อม UAT ก่อนขึ้น Production

---

## 5. แบบฟอร์มขอข้อมูลจากผู้ดูแลระบบ SQL Server 2014 (19 รายการ)

| ลำดับ | รายการที่ต้องขอจากทีม IT / DBA | ข้อมูลที่ได้รับ |
| :---: | :--- | :--- |
| 1 | ชื่อ SQL Server หรือ IP Address | |
| 2 | Port / Named Instance | |
| 3 | Database Name | |
| 4 | วิธี Authentication (SQL Auth / Windows Auth) | |
| 5 | บัญชี Read-Only สำหรับระบบ | |
| 6 | ชื่อ View, Table หรือ Stored Procedure | |
| 7 | ชื่อคอลัมน์ HN | |
| 8 | ชื่อคอลัมน์ผู้ป่วย (คำนำหน้า, ชื่อ, นามสกุล, วันเกิด, เพศ, เลขบัตร ปชช.) | |
| 9 | รูปแบบ HN ของโรงพยาบาลเอกชล (ความยาว, ขีด, ศูนย์นำหน้า) | |
| 10 | วิธีแยกมารดาและทารก | |
| 11 | วิธีเชื่อมความสัมพันธ์ระหว่างมารดาและทารก | |
| 12 | ชื่อคอลัมน์ AN (Admission Number) | |
| 13 | ชื่อคอลัมน์ Ward / หอผู้ป่วย | |
| 14 | รูปแบบวันที่ (ค.ศ. / พ.ศ. / Format) | |
| 15 | Character Encoding / Collation ภาษาไทย | |
| 16 | ตัวอย่างผล Query ที่ปกปิดข้อมูลส่วนบุคคลแล้ว (2–3 แถว) | |
| 17 | นโยบาย Network & Firewall (IP Allowlist / Port) | |
| 18 | นโยบาย TLS & Certificate ของ SQL Server 2014 (SP Level / CA) | |
| 19 | ข้อจำกัดจำนวนครั้งหรือช่วงเวลาที่อนุญาตให้ Query | |
