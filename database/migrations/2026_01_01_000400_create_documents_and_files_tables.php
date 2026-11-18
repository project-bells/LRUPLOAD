<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_th', 150);
            $table->string('name_en', 150)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 50)->unique();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('title', 255);
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->string('patient_owner_type', 30)->index(); // mother, child, mother_and_child
            $table->date('document_date')->index();
            $table->date('received_date')->nullable();
            $table->string('reference_number', 100)->nullable()->index();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('confidentiality_level', 30)->default('confidential');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('delete_reason')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'deleted_at']);
            $table->index(['document_type_id', 'document_date']);
        });

        Schema::create('document_patient_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('relationship_type', 30); // mother, child, primary
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_id', 'patient_id', 'relationship_type'], 'doc_patient_link_unique');
            $table->index('patient_id');
        });

        Schema::create('document_patient_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->longText('snapshot_data_encrypted');
            $table->timestamp('source_fetched_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'patient_id']);
        });

        Schema::create('stored_files', function (Blueprint $table) {
            $table->id();
            $table->string('original_name', 255);
            $table->string('stored_name', 120)->unique();
            $table->string('storage_disk', 50)->default('private_documents');
            $table->string('storage_path', 500);
            $table->string('mime_type', 120);
            $table->string('extension', 20);
            $table->unsignedBigInteger('file_size');
            $table->char('sha256_hash', 64)->index();
            $table->string('scan_status', 30)->default('not_scanned'); // not_scanned, clean, infected
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->foreignId('stored_file_id')->unique()->constrained('stored_files')->restrictOnDelete();
            $table->foreignId('previous_revision_id')->nullable()->constrained('document_revisions')->nullOnDelete();
            $table->string('status', 20)->default('current')->index(); // current, superseded, withdrawn
            // Enforces at DB level that each document can have at most ONE current revision (1 = current, NULL = non-current)
            $table->unsignedTinyInteger('is_current_flag')->nullable();
            $table->text('change_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'revision_number'], 'doc_rev_number_unique');
            $table->unique(['document_id', 'is_current_flag'], 'doc_single_current_rev_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_revisions');
        Schema::dropIfExists('stored_files');
        Schema::dropIfExists('document_patient_snapshots');
        Schema::dropIfExists('document_patient_links');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_types');
    }
};
