<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delete_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('request_reason');
            $table->string('status', 20)->default('pending')->index(); // pending, approved, rejected, cancelled
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('action', 60)->index();
            $table->unsignedBigInteger('document_id')->nullable()->index();
            $table->unsignedBigInteger('revision_id')->nullable()->index();
            $table->char('hn_hash', 64)->nullable()->index();
            $table->string('outcome', 20)->default('success')->index(); // success, failed, denied
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->char('request_id', 36)->nullable()->index();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group_name', 60)->index();
            $table->string('key', 100)->unique();
            $table->text('value_encrypted')->nullable();
            $table->text('value_plain')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('branding_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hospital_name_th', 150)->default('โรงพยาบาลเอกชล');
            $table->string('hospital_name_en', 150)->default('Aikchol Hospital');
            $table->string('department_name_th', 150)->default('แผนกห้องคลอด');
            $table->string('department_name_en', 150)->default('Labor Room Department');
            $table->string('logo_path', 255)->nullable();
            $table->string('primary_color', 20)->default('#004F85');
            $table->string('secondary_color', 20)->default('#3EC0C8');
            $table->string('accent_color', 20)->default('#FBB435');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_settings');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('delete_requests');
    }
};
