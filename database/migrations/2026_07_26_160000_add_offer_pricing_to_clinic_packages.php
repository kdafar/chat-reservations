<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packages become sellable offers.
 *
 * `default_price` keeps its meaning: the MAIN (list) price of the bundle.
 * `discount_price` is the optional offer price — when it is set, lower than the
 * main price and inside the offer window, it is what the patient actually pays,
 * and the difference is the saving we show them.
 *
 * The remaining columns let a package be published on the public website as an
 * offer (marketing copy, image, ordering) without exposing every internal
 * billing bundle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_packages', function (Blueprint $table) {
            $table->decimal('discount_price', 12, 3)->nullable()->after('default_price');

            // Offer validity window. Null = always on.
            $table->date('offer_starts_at')->nullable()->after('discount_price');
            $table->date('offer_ends_at')->nullable()->after('offer_starts_at');

            // Public website publishing.
            $table->boolean('is_public')->default(false)->index()->after('is_active');
            $table->json('description')->nullable()->after('name'); // {en, ar}
            $table->string('image_url', 2048)->nullable()->after('description');
            $table->unsignedInteger('sort_order')->default(0)->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_packages', function (Blueprint $table) {
            $table->dropColumn([
                'discount_price',
                'offer_starts_at',
                'offer_ends_at',
                'is_public',
                'description',
                'image_url',
                'sort_order',
            ]);
        });
    }
};
