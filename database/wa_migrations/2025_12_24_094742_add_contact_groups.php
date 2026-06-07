<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Groups Table
        Schema::create('contact_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 2. Contacts Table (Central repository of people)
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn')->index(); // E.164 format
            $table->string('name')->nullable();
            $table->string('locale')->default('en');
            $table->timestamps();

            // Unique constraint on phone number
            $table->unique('msisdn');
        });

        // 3. Pivot Table (Many-to-Many)
        Schema::create('contact_contact_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Prevent duplicate linking
            $table->unique(['contact_group_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_contact_group');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('contact_groups');
    }
};
