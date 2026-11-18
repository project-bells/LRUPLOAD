<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\FileStorageService;
use Illuminate\Foundation\Http\FormRequest;

class StageRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('documents.create_revision') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = app(FileStorageService::class)->maxFileSizeKb();

        return [
            'change_reason' => ['required', 'string', 'min:5', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', "max:{$maxKb}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'change_reason.required' => 'กรุณาระบุเหตุผลในการเพิ่ม Revision ใหม่',
            'change_reason.min' => 'กรุณาระบุเหตุผลอย่างน้อย 5 ตัวอักษร',
            'file.required' => 'กรุณาเลือกไฟล์เอกสารฉบับใหม่',
        ];
    }
}
