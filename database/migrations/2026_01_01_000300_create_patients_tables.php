<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('source_system', 60)->default('his_sqlsrv_2014');
            $table->string('patient_type', 20)->index(); // mother, child
            $table->text('hn_encrypted');
            $table->char('hn_hash', 64)->index();
            $table->text('title_name_encrypted')->nullable();
            $table->text('first_name_encrypted');
            $table->text('last_name_encrypted');
            $table->text('birth_date_encrypted')->nullable();
            $table->string('sex', 20)->nullable();
            $table->string('national_id_masked', 30)->nullable();
            $table->text('admission_number_encrypted')->nullable();
            $table->string('ward', 120)->nullable();
            $table->text('mother_hn_encrypted')->nullable();
            $table->char('mother_hn_hash', 64)->nullable()->index();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('source_fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'hn_hash'], 'patients_source_hn_hash_unique');
        });

        Schema::create('patient_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mother_patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('child_patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('relationship_source', 40)->default('manual_verified'); // his_sqlsrv, manual_verified
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['mother_patient_id', 'child_patient_id'], 'patient_rel_mother_child_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_relationships');
        Schema::dropIfExists('patients');
    }
};
