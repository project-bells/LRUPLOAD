<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class PatientData
{
    public function __construct(
        public string $hn,
        public string $patientType,
        public ?string $titleName,
        public string $firstName,
        public string $lastName,
        public ?string $birthDate,
        public ?string $sex,
        public ?string $nationalIdMasked,
        public ?string $admissionNumber,
        public ?string $ward,
        public ?string $motherHn,
        public ?string $childHn,
        public ?string $sourceUpdatedAt,
    ) {
    }

    public function displayName(): string
    {
        return trim(
            ($this->titleName ?? '') . $this->firstName . ' ' . $this->lastName
        );
    }

    /**
     * @return array<string, ?string>
     */
    public function toArray(): array
    {
        return [
            'hn' => $this->hn,
            'patient_type' => $this->patientType,
            'title_name' => $this->titleName,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'birth_date' => $this->birthDate,
            'sex' => $this->sex,
            'national_id_masked' => $this->nationalIdMasked,
            'admission_number' => $this->admissionNumber,
            'ward' => $this->ward,
            'mother_hn' => $this->motherHn,
            'child_hn' => $this->childHn,
            'source_updated_at' => $this->sourceUpdatedAt,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            hn: (string) ($data['hn'] ?? ''),
            patientType: (string) ($data['patient_type'] ?? 'mother'),
            titleName: isset($data['title_name']) ? (string) $data['title_name'] : null,
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: (string) ($data['last_name'] ?? ''),
            birthDate: isset($data['birth_date']) ? (string) $data['birth_date'] : null,
            sex: isset($data['sex']) ? (string) $data['sex'] : null,
            nationalIdMasked: isset($data['national_id_masked']) ? (string) $data['national_id_masked'] : null,
            admissionNumber: isset($data['admission_number']) ? (string) $data['admission_number'] : null,
            ward: isset($data['ward']) ? (string) $data['ward'] : null,
            motherHn: isset($data['mother_hn']) ? (string) $data['mother_hn'] : null,
            childHn: isset($data['child_hn']) ? (string) $data['child_hn'] : null,
            sourceUpdatedAt: isset($data['source_updated_at']) ? (string) $data['source_updated_at'] : null,
        );
    }
}
