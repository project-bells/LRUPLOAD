<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PermanentDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('documents.permanent_delete') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'current_password'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.current_password' => 'รหัสผ่านยืนยันตัวตนไม่ถูกต้อง',
            'reason.required' => 'กรุณาระบุเหตุผลการลบถาวรตามนโยบาย',
            'reason.min' => 'กรุณาระบุเหตุผลการลบถาวรอย่างน้อย 10 ตัวอักษร',
        ];
    }
}
