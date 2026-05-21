<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('services')) {
            return;
        }

        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->json('name');                                  // {en:"Food", ar:"مطاعم"}
            $t->string('slug');
            // Explicit non-default index name so it doesn't clash with the
            // legacy index that was renamed onto `service_types` (the original
            // services table was renamed in 2025_08_18_200843). MySQL scopes
            // index names per table so the old code worked, but SQLite (used
            // by the test suite) puts index names in a single namespace and
            // refuses the duplicate.
            $t->unique('slug', 'services_v2_slug_unique');
            $t->string('icon')->nullable();                    // heroicon/lucide key or image path
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
