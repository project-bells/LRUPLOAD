# คู่มือการติดตั้งและสำรองข้อมูล (Installation & Backup Guide)

**ระบบจัดเก็บเอกสารผู้ป่วย แผนกห้องคลอด โรงพยาบาลเอกชล**  
**Aikchol Hospital Labor Room Document Management System (LRDMS)**

---

## 1. ความต้องการของระบบ (System Requirements)

- **PHP:** 8.2 ขึ้นไป (แนะนำ 8.2 / 8.3 รุ่นเสถียร)
- **PHP Extensions:** `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `gd`, `ctype`, `xml`, `bcmath`, `zip` และ `pdo_sqlsrv` / `sqlsrv` (เมื่อเชื่อมต่อ SQL Server 2014)
- **Database หลัก:** MySQL 8.0+ (หรือ MariaDB 10.6+)
- **Database ต้นทาง (Read-Only):** Microsoft SQL Server 2014 (ผ่าน Connection แยกชื่อ `patient_sqlsrv`)
- **Web Server:** Apache 2.4+ (ตั้ง `DocumentRoot` ชี้ไปที่โฟลเดอร์ `public/` เท่านั้น) หรือ Nginx + PHP-FPM
- **Node.js:** 20+ (ใช้เฉพาะตอน Build Frontend Assets ผ่าน `npm run build`; เซิร์ฟเวอร์ Production ไม่จำเป็นต้องเปิด Vite dev server)
- **TLS/HTTPS:** บังคับใช้งาน HTTPS ภายในเครือข่ายโรงพยาบาล

---

## 2. ขั้นตอนการติดตั้ง (Windows Server & Linux)

### 2.1 เตรียมซอร์สโค้ดและ Dependencies
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### 2.2 สร้างและตั้งค่าไฟล์ `.env`
```bash
cp .env.example .env
php artisan key:generate
```

สร้างคีย์เฉพาะสำหรับ `PATIENT_HASH_KEY` (แยกจาก `APP_KEY` เพื่อความปลอดภัยสูงสุดของ `hn_hash`):
```bash
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```
นำค่าที่ได้ไปใส่ใน `.env` ที่ตัวแปร `PATIENT_HASH_KEY=`

ตั้งค่าหลักใน `.env` สำหรับ Production:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://lrdms.aikchol.local
APP_TIMEZONE=Asia/Bangkok

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aikchol_lrdms
DB_USERNAME=lrdms_app
DB_PASSWORD=<STRONG_MYSQL_PASSWORD>

SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

> **ห้าม Commit ไฟล์ `.env` รหัสผ่าน หรือ IP เซิร์ฟเวอร์จริงลงใน Git เด็ดขาด**

### 2.3 เตรียมฐานข้อมูล MySQL และ Migration
สร้างฐานข้อมูล MySQL แบบ `utf8mb4_unicode_ci` และสร้างบัญชีผู้ใช้เฉพาะ (`lrdms_app`) ตามหลัก Least Privilege:
```sql
CREATE DATABASE aikchol_lrdms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lrdms_app'@'localhost' IDENTIFIED BY '<STRONG_PASSWORD>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON aikchol_lrdms.* TO 'lrdms_app'@'localhost';
FLUSH PRIVILEGES;
```

> **แนะนำเพิ่มเติมด้าน Audit Log Integrity:** หลังรัน Migration เสร็จสิ้น สามารถใช้สิทธิ์ระดับตารางเพื่อจำกัดบัญชี Runtime ไม่ให้มีสิทธิ์ `UPDATE`/`DELETE` บนตาราง `audit_logs` (อนุญาตเฉพาะ `SELECT, INSERT`)

รัน Migration และ Seeder พื้นฐาน:
```bash
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=InitialDataSeeder --force
```

### 2.4 ตั้งค่าพื้นที่จัดเก็บไฟล์นอก Public Directory
- โฟลเดอร์ไฟล์เอกสารอยู่ที่ `storage/app/private/documents` (หรือกำหนดตำแหน่งแยกดิสก์ผ่าน `DOCUMENT_STORAGE_PATH` ใน `.env`)
- โฟลเดอร์ชั่วคราวอยู่ที่ `storage/app/private/tmp_uploads`
- **ห้าม** สร้าง Symlink จาก `public/` มายัง `storage/app/private` เด็ดขาด
- บน Linux กำหนดสิทธิ์ `chmod -R 750 storage bootstrap/cache` และเป็นเจ้าของโดย Web Server User (`www-data` / `nginx`)

### 2.5 ตั้งค่า Web Server (`DocumentRoot` ต้องชี้ที่ `public/`)
- **Apache:** ตั้ง `DocumentRoot "/path/to/lrd_upload/public"` และ `AllowOverride All`
- **Nginx:** ตั้ง `root /path/to/lrd_upload/public;` และ `try_files $uri $uri/ /index.php?$query_string;`

### 2.6 ตั้งค่า Cron / Task Scheduler สำหรับล้างไฟล์ชั่วคราว
- **Linux Cron:**
  ```cron
  * * * * * cd /path/to/lrd_upload && php artisan schedule:run >> /dev/null 2>&1
  ```
- **Windows Task Scheduler:** สร้าง Scheduled Task รันทุก 1 นาที ด้วยคำสั่ง `php C:\path\to\lrd_upload\artisan schedule:run` (จะรันคำสั่ง `documents:purge-tmp-uploads` อัตโนมัติทุกชั่วโมง)

### 2.7 Optimize Cache สำหรับ Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 3. แนวทางการสำรองและกู้คืนข้อมูล (Backup & Restore Strategy)

1. **สำรองข้อมูลแบบสัมพันธ์กัน (Coordinated Backup):**
   - สำรองฐานข้อมูล MySQL ด้วย `mysqldump --single-transaction --routines --triggers aikchol_lrdms`
   - สำรองพื้นที่จัดเก็บเอกสาร (`storage/app/private/documents` และ `storage/app/private/branding`) ในช่วงเวลาเดียวกัน
2. **การเข้ารหัส Backup:** เข้ารหัสไฟล์สำรองทุกชุดด้วย AES-256 / GPG ก่อนเก็บลง Storage ปลายทางและ Off-site Backup
3. **การสำรอง Encryption Keys แยกต่างหาก:**
   - เก็บค่า `APP_KEY` และ `PATIENT_HASH_KEY` ไว้ใน Password Vault / Offline Encrypted Media ที่แยกจากไฟล์ Backup ฐานข้อมูล
   - หากสูญเสีย `APP_KEY` จะไม่สามารถถอดรหัสข้อมูลผู้ป่วยในตาราง `patients` และ `document_patient_snapshots` ได้
4. **Retention Policy ที่แนะนำ:**
   - Daily Backup: เก็บ 30 วัน
   - Weekly Backup: เก็บ 12 สัปดาห์
   - Monthly Backup: เก็บตามระยะเวลากฎหมายเวชระเบียนของโรงพยาบาล (เช่น 5–10 ปี)
5. **การตรวจสอบความสมบูรณ์และซ้อม Restore:**
   - ตรวจ SHA-256 checksum ของไฟล์เอกสารเทียบกับคอลัมน์ `stored_files.sha256_hash` เป็นระยะ
   - ทดสอบ Restore ลง Staging Server อย่างน้อยไตรมาสละ 1 ครั้ง พร้อมบันทึกผลการทดสอบ
