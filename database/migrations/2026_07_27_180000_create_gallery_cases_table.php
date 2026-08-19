<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Before / after cases shown on the public site.
 *
 * The single most-asked-for page on an aesthetic clinic's website: proof of
 * results. Each row is one patient case with a pair of images, optionally tied
 * to the treatment category, the doctor who did the work and the branch.
 *
 * Images are URLs (same approach as clinic_packages.image_url) so the clinic
 * can point at whatever storage they already use for patient photography.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_cases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();

            $table->json('title');
            $table->json('summary')->nullable();

            $table->string('before_image_url', 2048);
            $table->string('after_image_url', 2048);

            // "3 sessions over 8 weeks" — the context that makes a result
            // credible instead of a stock photo.
            $table->json('protocol')->nullable();

            // Patient consent is a legal precondition for publishing these, so
            // it is recorded rather than assumed.
            $table->boolean('consent_on_file')->default(false);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_cases');
    }
};
