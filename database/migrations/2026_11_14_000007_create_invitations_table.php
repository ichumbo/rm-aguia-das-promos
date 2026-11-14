<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 32); // assisted | official_api | simulated
            $table->string('idempotency_key', 120)->unique();
            $table->string('active_dedup_key', 120)->nullable()->unique();
            $table->boolean('is_resend')->default(false);
            $table->string('resend_reason')->nullable();
            $table->string('provider_message_id', 191)->nullable()->index();
            $table->string('status', 32)->index();
            $table->string('status_origin', 32)->default('system');
            $table->string('record_source', 24)->default('real')->index();
            $table->text('rendered_message');
            $table->text('wa_me_url')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('uncertain_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
