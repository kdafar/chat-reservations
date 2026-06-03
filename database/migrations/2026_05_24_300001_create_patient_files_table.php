<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_files', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('visit_id')->nullable()->constrained('visits');

            // Branch scope for staff access control via BelongsToBranchScope.
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            // Storage path relative to the disk (e.g. 'patient-files/{patient_id}/{uuid}.pdf')
            $table->string('file_path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);

            // Categories — extend later if needed. Keep enum tight for now.
            $table->enum('category', [
                'lab_report',
                'prescription',
                'imaging',
                'insurance_card',
                'consent_form',
                'referral',
                'discharge_summary',
                'other',
            ])->default('other')->index();

            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();

            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'category']);
            $table->index(['visit_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_files');
    }
};
