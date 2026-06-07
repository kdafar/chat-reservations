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
        Schema::create('promotional_campaign_recipients', function (Blueprint $table) {
            $table->id();
            // Explicit short index name: the wam_ prefix + long table/column
            // would otherwise exceed MySQL's 64-char identifier limit.
            $table->foreignId('promotional_campaign_id')
                ->constrained('promotional_campaigns', indexName: 'wam_pcr_campaign_id_foreign')
                ->cascadeOnDelete();

            $table->string('msisdn', 32); // E.164
            $table->string('status', 32)->default('pending'); // pending/sent/delivered/failed/etc.
            $table->string('wa_message_id')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotional_campaign_recipients');
    }
};
