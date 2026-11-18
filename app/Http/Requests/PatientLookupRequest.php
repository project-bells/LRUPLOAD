<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\HnSecurityService;
use Illuminate\Foundation\Http\FormRequest;

class PatientLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('patients.lookup') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('hn')) {
            $hnSecurity = app(HnSecurityService::class);
            $this->merge([
                'hn' => $hnSecurity->normalize((string) $this->input('hn')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hnSecurity = app(HnSecurityService::class);

        return [
            'hn' => [
                'required',
                'string',
                'min:' . (int) config('patient_lookup.hn_min_length', 4),
                'max:' . (int) config('patient_lookup.hn_max_length', 20),
                static function (string $attribute, mixed $value, \Closure $fail) use ($hnSecurity): void {
                    if (! is_string($value) || ! $hnSecurity->isValid($value)) {
                        $fail('รูปแบบ HN ไม่ถูกต้อง');
                    }
                },
            ],
            'patient_type' => ['nullable', 'string', 'in:auto,mother,child'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hn.required' => 'กรุณากรอกหมายเลข HN',
            'hn.min' => 'รูปแบบ HN ไม่ถูกต้อง',
            'hn.max' => 'รูปแบบ HN ไม่ถูกต้อง',
        ];
    }
}
