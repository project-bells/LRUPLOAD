<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConfidentialityLevel;
use App\Enums\PatientOwnerType;
use App\Enums\RevisionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_number',
        'department_id',
        'title',
        'document_type_id',
        'patient_owner_type',
        'document_date',
        'received_date',
        'reference_number',
        'description',
        'notes',
        'confidentiality_level',
        'created_by',
        'deleted_by',
        'delete_reason',
    ];

    protected function casts(): array
    {
        return [
            'patient_owner_type' => PatientOwnerType::class,
            'confidentiality_level' => ConfidentialityLevel::class,
            'document_date' => 'date',
            'received_date' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function patients(): BelongsToMany
    {
        return $this->belongsToMany(Patient::class, 'document_patient_links')
            ->withPivot(['relationship_type', 'created_at']);
    }

    public function patientLinks(): HasMany
    {
        return $this->hasMany(DocumentPatientLink::class);
    }

    public function patientSnapshots(): HasMany
    {
        return $this->hasMany(DocumentPatientSnapshot::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class)->orderByDesc('revision_number');
    }

    public function currentRevision(): HasOne
    {
        return $this->hasOne(DocumentRevision::class)
            ->where('status', RevisionStatus::Current->value);
    }

    public function deleteRequests(): HasMany
    {
        return $this->hasMany(DeleteRequest::class)->latest();
    }

    public function pendingDeleteRequest(): HasOne
    {
        return $this->hasOne(DeleteRequest::class)->where('status', 'pending');
    }

    public function primaryPatient(): ?Patient
    {
        $this->loadMissing('patients');

        return $this->patients->first();
    }
}
