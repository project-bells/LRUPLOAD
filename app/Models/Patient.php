<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PatientType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_system',
        'patient_type',
        'hn_encrypted',
        'hn_hash',
        'title_name_encrypted',
        'first_name_encrypted',
        'last_name_encrypted',
        'birth_date_encrypted',
        'sex',
        'national_id_masked',
        'admission_number_encrypted',
        'ward',
        'mother_hn_encrypted',
        'mother_hn_hash',
        'source_updated_at',
        'source_fetched_at',
    ];

    protected $hidden = [
        'hn_encrypted',
        'title_name_encrypted',
        'first_name_encrypted',
        'last_name_encrypted',
        'birth_date_encrypted',
        'admission_number_encrypted',
        'mother_hn_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'patient_type' => PatientType::class,
            'hn_encrypted' => 'encrypted',
            'title_name_encrypted' => 'encrypted',
            'first_name_encrypted' => 'encrypted',
            'last_name_encrypted' => 'encrypted',
            'birth_date_encrypted' => 'encrypted',
            'admission_number_encrypted' => 'encrypted',
            'mother_hn_encrypted' => 'encrypted',
            'source_updated_at' => 'datetime',
            'source_fetched_at' => 'datetime',
        ];
    }

    public function getHnAttribute(): string
    {
        return (string) ($this->hn_encrypted ?? '');
    }

    public function getTitleNameAttribute(): ?string
    {
        return $this->title_name_encrypted;
    }

    public function getFirstNameAttribute(): string
    {
        return (string) ($this->first_name_encrypted ?? '');
    }

    public function getLastNameAttribute(): string
    {
        return (string) ($this->last_name_encrypted ?? '');
    }

    public function getBirthDateAttribute(): ?string
    {
        return $this->birth_date_encrypted;
    }

    public function getAdmissionNumberAttribute(): ?string
    {
        return $this->admission_number_encrypted;
    }

    public function getMotherHnAttribute(): ?string
    {
        return $this->mother_hn_encrypted;
    }

    public function getDisplayNameAttribute(): string
    {
        return trim(($this->title_name ?? '') . $this->first_name . ' ' . $this->last_name);
    }

    public function childRelationships(): HasMany
    {
        return $this->hasMany(PatientRelationship::class, 'mother_patient_id');
    }

    public function motherRelationships(): HasMany
    {
        return $this->hasMany(PatientRelationship::class, 'child_patient_id');
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(
            Patient::class,
            'patient_relationships',
            'mother_patient_id',
            'child_patient_id'
        )->withPivot(['relationship_source', 'verified_by', 'verified_at'])->withTimestamps();
    }

    public function mothers(): BelongsToMany
    {
        return $this->belongsToMany(
            Patient::class,
            'patient_relationships',
            'child_patient_id',
            'mother_patient_id'
        )->withPivot(['relationship_source', 'verified_by', 'verified_at'])->withTimestamps();
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_patient_links')
            ->withPivot(['relationship_type', 'created_at']);
    }

    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentPatientLink::class);
    }

    /**
     * Build a safe snapshot array (for encryption into document_patient_snapshots).
     *
     * @return array<string, mixed>
     */
    public function toSnapshotPayload(): array
    {
        return [
            'source_system' => $this->source_system,
            'patient_type' => $this->patient_type?->value ?? (string) $this->patient_type,
            'hn' => $this->hn,
            'title_name' => $this->title_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'birth_date' => $this->birth_date,
            'sex' => $this->sex,
            'national_id_masked' => $this->national_id_masked,
            'admission_number' => $this->admission_number,
            'ward' => $this->ward,
            'mother_hn' => $this->mother_hn,
            'source_updated_at' => $this->source_updated_at?->toIso8601String(),
            'source_fetched_at' => $this->source_fetched_at?->toIso8601String(),
        ];
    }
}
