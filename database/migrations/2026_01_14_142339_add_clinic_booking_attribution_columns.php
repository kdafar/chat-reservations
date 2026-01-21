<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // 1. Patient ID (Link to User/Patient)
            // We check if the column exists first to prevent crashes
            if (! Schema::hasColumn('bookings', 'patient_id')) {
                $table->unsignedBigInteger('patient_id')
                    ->nullable()
                    ->index()
                    // We use 'after' for cleanliness, but wrap in try-catch logic implicitly by checking column existence
                    // If 'contact_id' is missing in your DB, remove the ->after() chain.
                    ->after('contact_id');
            }

            // 2. Source (Channel: web, whatsapp, call)
            if (! Schema::hasColumn('bookings', 'source')) {
                $table->string('source', 20)
                    ->nullable()
                    ->index() // Indexed for the "Bookings by Source" report
                    ->after('patient_id');
            }

            // 3. Source Reference (e.g., WhatsApp Message ID)
            if (! Schema::hasColumn('bookings', 'source_ref')) {
                $table->string('source_ref', 191)
                    ->nullable()
                    ->index() // Indexed for fast lookups by message ID
                    ->after('source');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'source_ref')) {
                $table->dropColumn('source_ref');
            }
            if (Schema::hasColumn('bookings', 'source')) {
                $table->dropColumn('source');
            }
            if (Schema::hasColumn('bookings', 'patient_id')) {
                $table->dropColumn('patient_id');
            }
        });
    }
};
