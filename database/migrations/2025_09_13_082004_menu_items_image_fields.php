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
        Schema::table('menu_items', function (Blueprint $t) {
            $t->string('image_src_url', 512)->nullable();
            $t->string('image_src_hash', 64)->nullable()->index();       // sha1(url)
            $t->string('image_fingerprint', 64)->nullable()->index();    // sha256(content)
            $t->string('image_etag', 128)->nullable();
            $t->string('image_last_modified', 64)->nullable();
            $t->timestamp('image_fetched_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $t) {
            $t->dropColumn([
                'image_src_url', 'image_src_hash', 'image_fingerprint',
                'image_etag', 'image_last_modified', 'image_fetched_at',
            ]);
        });
    }
};
