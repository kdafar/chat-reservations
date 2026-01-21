<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PATIENTS (Master Record)
        // Created immediately upon booking or walk-in registration.
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete(); // Clinic ownership

            $table->string('name');
            $table->string('phone')->index(); // MSISDN (Primary identifier for lookups)
            $table->string('email')->nullable();

            // Demographics (Optional but good for medical history)
            $table->date('dob')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('civil_id')->nullable();

            $table->text('medical_alerts')->nullable(); // Allergies, chronic conditions (High visibility)
            $table->text('notes')->nullable(); // Admin notes

            $table->timestamps();

            // Ensure unique phone per clinic to prevent duplicates
            $table->unique(['partner_id', 'phone']);
        });

        // 2. DOCTORS
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();

            // Primary Branch (Doctor belongs here, but can move)
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            // Identity
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('avatar_path')->nullable();

            // Professional
            $table->string('specialty'); // e.g. "Dermatology"
            $table->string('license_number')->nullable();
            $table->text('bio')->nullable();
            $table->decimal('consultation_fee', 10, 2)->default(0);

            // Schedule Rules
            // e.g. {"mon": "09:00-17:00", "tue": "09:00-12:00"}
            $table->json('working_hours')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. VISITS (The Medical Record / Encounter)
        // This is created automatically when Reception marks "Check-In".
        Schema::create('visits', function (Blueprint $table) {
            $table->id();

            // Links
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();

            // Dynamic Room Assignment (The room used for THIS specific visit)
            $table->foreignId('restaurant_table_id')
                ->nullable()
                ->constrained('restaurant_tables')
                ->nullOnDelete();

            // Link to Booking (Nullable because walk-ins exist)
            // Assumes 'bookings' table exists.
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            // Workflow Status
            // created -> checked_in -> in_progress -> completed -> cancelled
            $table->string('status')->default('created');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // --- Clinical Data (The "Petition" data) ---

            // Intake
            $table->string('chief_complaint')->nullable(); // Why are they here?
            $table->json('vitals')->nullable(); // {"bp": "120/80", "temp": 37.5, "weight": 70}

            // Doctor Notes
            $table->text('history')->nullable();
            $table->text('examination')->nullable();
            $table->text('diagnosis')->nullable();

            // Treatment (Free Text / Phase 1)
            // Stored as JSON array: [{"medicine": "Panadol", "dosage": "500mg", "instruction": "After food"}]
            $table->json('prescriptions')->nullable();

            // Labs/Imaging (Free Text)
            // Stored as JSON array: ["Chest X-Ray", "CBC Blood Test"]
            $table->json('lab_requests')->nullable();

            $table->text('notes')->nullable(); // Internal/Private notes

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('patients');
    }
};
