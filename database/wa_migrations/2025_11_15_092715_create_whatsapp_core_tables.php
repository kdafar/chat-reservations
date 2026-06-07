<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * 1) Accounts (one per customer workspace)
         */
        Schema::create('wa_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Our label, e.g. "Zad by Majestic"

            // Meta business / WABA IDs
            $table->string('external_business_id')->nullable(); // e.g. WhatsApp Business Account ID

            // Owner references (adjust to your own structure if needed)
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table
                ->foreign('owner_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Optional: link to your internal customer / site / tenant
            $table->unsignedBigInteger('customer_id')->nullable();

            $table->string('timezone', 64)->default('Asia/Kuwait');
            $table->string('status', 32)->default('active'); // active, paused, disconnected

            $table->json('meta_raw')->nullable(); // full WABA payload / extra info

            $table->timestamps();
        });

        /**
         * 2) Credentials (tokens etc.)
         */
        Schema::create('wa_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wa_account_id')
                ->constrained('wa_accounts')
                ->cascadeOnDelete();

            $table->string('type', 64); // system_user_token, page_token, etc.
            $table->text('token');      // encrypted string via encrypt()

            $table->timestamp('expires_at')->nullable();
            $table->json('meta_raw')->nullable();

            $table->timestamps();
        });

        /**
         * 3) Numbers (Cloud API phone numbers)
         */
        Schema::create('wa_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wa_account_id')
                ->constrained('wa_accounts')
                ->cascadeOnDelete();

            $table->foreignId('credential_id')
                ->nullable()
                ->constrained('wa_credentials')
                ->nullOnDelete();

            // From Meta
            $table->string('phone_number_id')->index();       // Meta phone_number_id
            $table->string('display_phone_number');           // +965 5xx xxx
            $table->string('verified_name')->nullable();
            $table->string('waba_id')->nullable();

            // Health
            $table->string('quality_rating', 32)->nullable();     // GREEN, YELLOW…
            $table->string('messaging_limit_tier', 64)->nullable(); // e.g. 1K, 10K
            $table->string('account_mode', 32)->nullable();       // live, test

            $table->string('status', 32)->default('connected');   // connected, disconnected, error

            $table->json('meta_raw')->nullable(); // full /phone_numbers payload

            $table->timestamps();

            $table->unique(['wa_account_id', 'phone_number_id'], 'wa_numbers_account_phone_unique');
        });

        /**
         * 4) Contacts
         */
        Schema::create('wa_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wa_account_id')
                ->constrained('wa_accounts')
                ->cascadeOnDelete();

            $table->string('wa_id'); // WhatsApp JID, e.g. 9655xxxxxxx
            $table->string('phone', 32)->nullable();
            $table->string('name')->nullable();

            $table->json('meta_raw')->nullable();

            $table->timestamps();

            $table->unique(['wa_account_id', 'wa_id'], 'wa_contacts_account_waid_unique');
            $table->index(['wa_account_id', 'phone']);
        });

        /**
         * 5) Conversations
         */
        Schema::create('wa_conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wa_account_id')
                ->constrained('wa_accounts')
                ->cascadeOnDelete();

            $table->foreignId('wa_number_id')
                ->constrained('wa_numbers')
                ->cascadeOnDelete();

            $table->foreignId('contact_id')
                ->constrained('wa_contacts')
                ->cascadeOnDelete();

            $table->string('status', 32)->default('open'); // open, resolved, snoozed

            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_incoming_at')->nullable();
            $table->timestamp('last_outgoing_at')->nullable();

            // Which agent owns it (if any)
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table
                ->foreign('assigned_to_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->json('meta_raw')->nullable(); // extra info / tags later

            $table->timestamps();

            $table->index(['wa_account_id', 'status']);
        });

        /**
         * 6) Messages
         */
        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wa_account_id')
                ->constrained('wa_accounts')
                ->cascadeOnDelete();

            $table->foreignId('wa_number_id')
                ->constrained('wa_numbers')
                ->cascadeOnDelete();

            $table->foreignId('conversation_id')
                ->constrained('wa_conversations')
                ->cascadeOnDelete();

            $table->string('direction', 8); // in / out
            $table->string('type', 32)->default('text'); // text, template, image, etc.

            $table->text('body')->nullable();

            $table->text('media_url')->nullable();
            $table->string('media_id')->nullable();

            $table->string('template_name')->nullable();

            $table->string('meta_message_id')->nullable()->index(); // WhatsApp message id

            $table->string('status', 32)->nullable(); // sent, delivered, read, failed
            $table->string('error_code', 32)->nullable();
            $table->text('error_message')->nullable();

            // Optional separate timestamps (created_at is the DB insert time)
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->json('meta_raw')->nullable();

            $table->timestamps();

            $table->index(['wa_account_id', 'direction']);
        });

        /**
         * 7) Templates mirror
         */
        Schema::create('wa_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wa_account_id')
                ->constrained('wa_accounts')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('language', 16);
            $table->string('category', 32)->nullable(); // UTILITY, MARKETING, AUTHENTICATION
            $table->string('status', 32)->nullable();   // APPROVED, REJECTED, PENDING

            $table->json('components')->nullable(); // header/body/buttons etc.
            $table->json('meta_raw')->nullable();

            $table->timestamps();

            $table->unique(
                ['wa_account_id', 'name', 'language'],
                'wa_templates_account_name_lang_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_templates');
        Schema::dropIfExists('wa_messages');
        Schema::dropIfExists('wa_conversations');
        Schema::dropIfExists('wa_contacts');
        Schema::dropIfExists('wa_numbers');
        Schema::dropIfExists('wa_credentials');
        Schema::dropIfExists('wa_accounts');
    }
};
