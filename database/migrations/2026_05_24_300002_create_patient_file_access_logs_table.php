<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_file_access_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_file_id')->constrained('patient_files')->cascadeOnDelete();
            $table->unsignedBigInteger('accessed_by_user_id')->nullable();

            // 'view' = preview/inline open, 'download' = file pulled, 'delete' = soft-deleted, 'upload' = created.
            $table->enum('action', ['view', 'download', 'delete', 'upload'])->index();

            $table->dateTime('accessed_at');
            $table->string('ip_address', 45)->nullable();      // IPv6 max
            $table->string('user_agent', 500)->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['patient_file_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_file_access_logs');
    }
};
