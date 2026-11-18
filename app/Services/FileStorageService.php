<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FileScannerInterface;
use App\Models\StoredFile;
use App\Models\SystemSetting;
use App\Models\User;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FileStorageService
{
    private const MAX_IMAGE_WIDTH = 10000;
    private const MAX_IMAGE_HEIGHT = 10000;
    private const MAX_IMAGE_PIXELS = 40000000;

    public function __construct(
        private readonly FileScannerInterface $scanner,
    ) {
    }

    /**
     * Resolve effective max file size (KB) from SystemSetting (runtime) with config fallback.
     */
    public function maxFileSizeKb(): int
    {
        try {
            $setting = SystemSetting::query()->where('key', 'max_file_size_kb')->first();
            if ($setting !== null && is_numeric($setting->value_plain)) {
                return max(512, min(102400, (int) $setting->value_plain));
            }
        } catch (\Throwable) {
            // Fallback if table unavailable
        }

        return (int) config('documents.max_file_size_kb', 20480);
    }

    /**
     * Validate and stage an uploaded file into private temporary storage (outside public/).
     * Returns staged metadata stored in session for the Review Before Save step.
     *
     * @return array<string, mixed>
     */
    public function stageUpload(UploadedFile $file, User $uploader): array
    {
        $rawOriginalName = $file->getClientOriginalName();
        if (str_contains($rawOriginalName, "\0") || str_contains($rawOriginalName, '..')) {
            throw ValidationException::withMessages([
                'file' => 'ชื่อไฟล์ไม่ปลอดภัย กรุณาเปลี่ยนชื่อไฟล์และอัปโหลดใหม่อีกครั้ง',
            ]);
        }

        $originalName = $this->sanitizeOriginalFilename($rawOriginalName);
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = (array) config('documents.allowed_extensions', ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx']);

        if ($extension === '' || ! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => 'ชนิดไฟล์ไม่ได้รับอนุญาต รองรับเฉพาะ: ' . strtoupper(implode(', ', $allowedExtensions)),
            ]);
        }

        // Reject suspicious double extensions (e.g. report.php.pdf, payload.exe.jpg)
        if ($this->hasDangerousDoubleExtension($originalName)) {
            throw ValidationException::withMessages([
                'file' => 'ชื่อไฟล์ไม่ปลอดภัย กรุณาเปลี่ยนชื่อไฟล์และอัปโหลดใหม่อีกครั้ง',
            ]);
        }

        $maxKb = $this->maxFileSizeKb();
        $fileSize = (int) ($file->getSize() ?? 0);
        if ($fileSize <= 0 || $fileSize > ($maxKb * 1024)) {
            throw ValidationException::withMessages([
                'file' => "ขนาดไฟล์ต้องไม่เกิน {$maxKb} KB",
            ]);
        }

        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_file($realPath)) {
            throw ValidationException::withMessages([
                'file' => 'ไม่สามารถอ่านไฟล์ที่อัปโหลดได้ กรุณาลองใหม่อีกครั้ง',
            ]);
        }

        // Inspect real MIME type from file binary content (never trust client header alone)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = strtolower((string) $finfo->file($realPath));

        $allowedMimesByExt = (array) config('documents.allowed_mimes', []);
        $allowedMimes = (array) ($allowedMimesByExt[$extension] ?? []);

        if (! in_array($detectedMime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => 'เนื้อหาไฟล์ไม่ตรงกับนามสกุลไฟล์ที่ระบุ หรือเป็นไฟล์ที่ไม่รองรับ',
            ]);
        }

        // Reject executable or script signatures embedded at the beginning of the file
        if ($this->containsExecutableSignature($realPath, $extension)) {
            throw ValidationException::withMessages([
                'file' => 'ตรวจพบโครงสร้างไฟล์ที่ไม่ได้รับอนุญาต (Executable/Script) ระบบปฏิเสธการอัปโหลด',
            ]);
        }

        // Guard against image decompression bombs
        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $imgInfo = @getimagesize($realPath);
            if ($imgInfo === false || $imgInfo[0] <= 0 || $imgInfo[1] <= 0) {
                throw ValidationException::withMessages([
                    'file' => 'ไฟล์รูปภาพเสียหายหรือไม่ถูกต้อง',
                ]);
            }
            if (
                $imgInfo[0] > self::MAX_IMAGE_WIDTH ||
                $imgInfo[1] > self::MAX_IMAGE_HEIGHT ||
                ($imgInfo[0] * $imgInfo[1]) > self::MAX_IMAGE_PIXELS
            ) {
                throw ValidationException::withMessages([
                    'file' => 'ขนาดความละเอียดของรูปภาพสูงเกินกำหนด กรุณาลดขนาดภาพก่อนอัปโหลด',
                ]);
            }
        }

        // Optional antivirus hook
        $scanStatus = $this->scanner->scan($realPath);
        if ($scanStatus === 'infected') {
            throw ValidationException::withMessages([
                'file' => 'ตรวจพบไฟล์ต้องสงสัยจากระบบสแกนไวรัส ระบบปฏิเสธการอัปโหลด',
            ]);
        }

        $sha256 = (string) hash_file('sha256', $realPath);
        $tmpName = (string) Str::uuid() . '.' . $extension;
        $tmpRelativePath = 'u_' . $uploader->id . '/' . $tmpName;

        $stream = fopen($realPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open uploaded file stream.');
        }

        try {
            Storage::disk('tmp_uploads')->put($tmpRelativePath, $stream);
        } finally {
            fclose($stream);
        }

        return [
            'token' => (string) Str::uuid(),
            'tmp_path' => $tmpRelativePath,
            'original_name' => $originalName,
            'extension' => $extension,
            'mime_type' => $detectedMime,
            'file_size' => $fileSize,
            'sha256_hash' => $sha256,
            'scan_status' => $scanStatus,
            'uploaded_by' => $uploader->id,
            'staged_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Promote a staged temporary file into permanent private storage (outside public/)
     * and create a StoredFile metadata record.
     *
     * @param array<string, mixed> $staged
     */
    public function promoteStagedFile(array $staged, User $uploader): StoredFile
    {
        $tmpPath = $this->assertSafeRelativePath((string) ($staged['tmp_path'] ?? ''));

        if (! Storage::disk('tmp_uploads')->exists($tmpPath)) {
            throw new RuntimeException('ไม่พบไฟล์ชั่วคราวสำหรับบันทึก กรุณาอัปโหลดไฟล์ใหม่อีกครั้ง');
        }

        $extension = strtolower((string) ($staged['extension'] ?? 'pdf'));
        $storedName = (string) Str::uuid() . '.' . $extension;
        $subDir = now()->format('Y/m');
        $finalPath = $subDir . '/' . $storedName;

        $readStream = Storage::disk('tmp_uploads')->readStream($tmpPath);
        if ($readStream === false) {
            throw new RuntimeException('Unable to read staged file.');
        }

        try {
            Storage::disk('private_documents')->put($finalPath, $readStream);
        } finally {
            if (is_resource($readStream)) {
                fclose($readStream);
            }
        }

        // Verify SHA-256 integrity after moving to private storage
        $absFinalPath = Storage::disk('private_documents')->path($finalPath);
        $actualHash = (string) hash_file('sha256', $absFinalPath);
        if (! hash_equals((string) ($staged['sha256_hash'] ?? ''), $actualHash)) {
            Storage::disk('private_documents')->delete($finalPath);
            throw new RuntimeException('การตรวจสอบความถูกต้องของไฟล์ (SHA-256 Checksum) ล้มเหลว');
        }

        return StoredFile::query()->create([
            'original_name' => (string) ($staged['original_name'] ?? $storedName),
            'stored_name' => $storedName,
            'storage_disk' => 'private_documents',
            'storage_path' => $finalPath,
            'mime_type' => (string) ($staged['mime_type'] ?? 'application/pdf'),
            'extension' => $extension,
            'file_size' => (int) ($staged['file_size'] ?? 0),
            'sha256_hash' => $actualHash,
            'scan_status' => (string) ($staged['scan_status'] ?? 'not_scanned'),
            'uploaded_by' => $uploader->id,
            'created_at' => now(),
        ]);
    }

    /**
     * Safely delete a staged temporary upload file.
     */
    public function deleteStagedFile(?string $tmpPath): void
    {
        if ($tmpPath === null || $tmpPath === '') {
            return;
        }

        $safe = $this->assertSafeRelativePath($tmpPath);
        if (Storage::disk('tmp_uploads')->exists($safe)) {
            Storage::disk('tmp_uploads')->delete($safe);
        }
    }

    /**
     * Resolve absolute path on disk for a StoredFile while preventing path traversal.
     */
    public function resolveAbsolutePath(StoredFile $storedFile): string
    {
        $disk = Storage::disk($storedFile->storage_disk);
        $safeRelative = $this->assertSafeRelativePath($storedFile->storage_path);

        if (! $disk->exists($safeRelative)) {
            throw new RuntimeException('ไม่พบไฟล์เอกสารในพื้นที่จัดเก็บ');
        }

        $diskRoot = realpath($disk->path(''));
        $fileAbs = realpath($disk->path($safeRelative));

        if ($diskRoot === false || $fileAbs === false || ! str_starts_with($fileAbs, $diskRoot)) {
            throw new RuntimeException('Invalid storage path boundary detected.');
        }

        return $fileAbs;
    }

    /**
     * Resolve absolute path for a staged temporary file (for preview on Review Before Save page).
     */
    public function resolveStagedAbsolutePath(string $tmpPath): string
    {
        $disk = Storage::disk('tmp_uploads');
        $safeRelative = $this->assertSafeRelativePath($tmpPath);

        if (! $disk->exists($safeRelative)) {
            throw new RuntimeException('ไม่พบไฟล์ชั่วคราว');
        }

        $diskRoot = realpath($disk->path(''));
        $fileAbs = realpath($disk->path($safeRelative));

        if ($diskRoot === false || $fileAbs === false || ! str_starts_with($fileAbs, $diskRoot)) {
            throw new RuntimeException('Invalid temporary path boundary detected.');
        }

        return $fileAbs;
    }

    /**
     * Delete a physical file from private storage (only used on rollback or policy-approved permanent delete).
     */
    public function deletePrivateFile(string $diskName, string $storagePath): void
    {
        $safe = $this->assertSafeRelativePath($storagePath);
        if (Storage::disk($diskName)->exists($safe)) {
            Storage::disk($diskName)->delete($safe);
        }
    }

    private function assertSafeRelativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = ltrim($normalized, '/');

        if (
            $normalized === '' ||
            str_contains($normalized, '..') ||
            str_contains($normalized, "\0") ||
            preg_match('/^[A-Za-z]:/', $normalized) === 1
        ) {
            throw new RuntimeException('Unsafe file path rejected.');
        }

        return $normalized;
    }

    private function sanitizeOriginalFilename(string $filename): string
    {
        $base = basename(str_replace('\\', '/', $filename));
        // Strip control bytes, quotes, semicolons, and path separators to prevent header parameter injection
        $base = preg_replace('/[\x00-\x1F\x7F"\';\/\\\\]+/', '_', $base) ?? 'document';
        $base = trim($base, " ._");

        return Str::limit($base !== '' ? $base : 'document', 200, '');
    }

    private function hasDangerousDoubleExtension(string $filename): bool
    {
        $parts = explode('.', strtolower($filename));
        if (count($parts) <= 2) {
            return false;
        }

        $dangerous = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'js', 'vbs', 'ps1', 'dll', 'com', 'scr', 'msi', 'jsp', 'asp', 'aspx', 'cgi'];
        array_pop($parts); // remove final allowed extension

        foreach ($parts as $part) {
            if (in_array($part, $dangerous, true)) {
                return true;
            }
        }

        return false;
    }

    private function containsExecutableSignature(string $realPath, string $extension): bool
    {
        $handle = fopen($realPath, 'rb');
        if ($handle === false) {
            return true;
        }

        $head = (string) fread($handle, 512);
        fclose($handle);

        // Windows PE/MZ, Linux ELF, Mach-O, Shebang, PHP tags
        if (
            str_starts_with($head, 'MZ') ||
            str_starts_with($head, "\x7FELF") ||
            str_starts_with($head, '#!') ||
            stripos($head, '<?php') !== false ||
            stripos($head, '<?=') !== false
        ) {
            return true;
        }

        // Strict PDF magic header check
        if ($extension === 'pdf' && ! str_starts_with(ltrim($head), '%PDF-')) {
            return true;
        }

        return false;
    }
}
