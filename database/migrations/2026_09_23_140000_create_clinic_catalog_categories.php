<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories for the clinic's catalogue (services, items, packages), so the
 * visit screen can offer "pick a category, tap a card" instead of search.
 *
 * Named by the clinic (bilingual, like item names), owned per clinic
 * (partner) like the catalogue itself. An item or package with no category
 * shows under "Other". Additive only: deleting a category just un-files its
 * items.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clinic_catalog_categories')) {
            Schema::create('clinic_catalog_categories', function (Blueprint $t) {
                $t->id();
                $t->foreignId('partner_id')->nullable()->index();
                $t->json('name');                      // {"en": "...", "ar": "..."}
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        foreach (['clinic_items', 'clinic_packages'] as $table) {
            if (! Schema::hasColumn($table, 'category_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('category_id')->nullable()->index()
                        ->constrained('clinic_catalog_categories')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['clinic_items', 'clinic_packages'] as $table) {
            if (Schema::hasColumn($table, 'category_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropConstrainedForeignId('category_id');
                });
            }
        }
        Schema::dropIfExists('clinic_catalog_categories');
    }
};
