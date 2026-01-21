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
        Schema::create('whatsapp_triggers', function (Blueprint $table) {
            $table->id();

            // 'keyword' -> Responds to a specific word (e.g., "menu", "about")
            // 'welcome' -> Sent to a new user on first contact
            // 'finale'  -> Sent after a successful booking
            // 'fallback'-> Sent when no keyword or command is understood
            $table->string('type')->default('keyword')->index()
                ->comment('Type of trigger (keyword, welcome, finale, fallback)');

            // The trigger word (e.g., "menu", "hours", "about"). Nullable for non-keyword types.
            $table->string('keyword')->nullable()->index()
                ->comment('The exact keyword to trigger this response (if type=keyword)');

            // Localized responses
            $table->text('response_message_en')->nullable();
            $table->text('response_message_ar')->nullable();

            // For future use (e.g., to send a PDF menu or image)
            // 'text', 'image_url', 'document_url'
            $table->string('response_type')->default('text');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_triggers');
    }
};
