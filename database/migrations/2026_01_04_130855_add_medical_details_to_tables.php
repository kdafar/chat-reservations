<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update Visits Table (Transaction info)
        Schema::table('visits', function (Blueprint $table) {
            $table->date('follow_up_date')->nullable()->after('diagnosis');
            $table->integer('sick_leave_days')->nullable()->after('follow_up_date'); // e.g., 2 days off
            $table->text('patient_instructions')->nullable()->after('prescriptions'); // Advice printed on Rx
        });

        // 2. Update Patients Table (Demographics)
        // Check if columns exist first to avoid errors if you already added them
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'dob')) {
                $table->date('dob')->nullable();
            }
            if (! Schema::hasColumn('patients', 'gender')) {
                $table->string('gender')->nullable(); // 'male', 'female'
            }
            if (! Schema::hasColumn('patients', 'allergies')) {
                $table->text('allergies')->nullable(); // e.g., "Penicillin, Peanuts"
            }
            if (! Schema::hasColumn('patients', 'blood_group')) {
                $table->string('blood_group')->nullable(); // e.g., "O+"
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['follow_up_date', 'sick_leave_days', 'patient_instructions']);
        });

        // Note: Be careful dropping patient columns if they contain data
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['dob', 'gender', 'allergies', 'blood_group']);
        });
    }
};
