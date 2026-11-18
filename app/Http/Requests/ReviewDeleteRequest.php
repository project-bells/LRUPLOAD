<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('delete_requests.review') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approve,reject'],
            'review_note' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'review_note.required_if' => 'กรุณาระบุเหตุผลเมื่อปฏิเสธคำขอลบ',
        ];
    }
}
