<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\FileStorageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StageDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('documents.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = app(FileStorageService::class)->maxFileSizeKb();

        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'related_patient_id' => ['nullable', 'integer', 'exists:patients,id', 'different:patient_id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'patient_owner_type' => ['required', 'string', Rule::in(['mother', 'child', 'mother_and_child'])],
            'title' => ['required', 'string', 'max:255'],
            'document_date' => ['required', 'date'],
            'received_date' => ['nullable', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'confidentiality_level' => ['required', 'string', Rule::in(['normal', 'confidential', 'strictly_confidential'])],
            'file' => ['required', 'file', "max:{$maxKb}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'กรุณากรอกชื่อเอกสาร',
            'document_type_id.required' => 'กรุณาเลือกประเภทเอกสาร',
            'document_date.required' => 'กรุณาระบุวันที่เอกสาร',
            'file.required' => 'กรุณาเลือกไฟล์เอกสารที่ต้องการอัปโหลด',
        ];
    }
}
