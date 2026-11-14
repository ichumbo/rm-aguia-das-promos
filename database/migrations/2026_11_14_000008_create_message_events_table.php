<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->nullable()->constrained('invitations')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('provider_event_id', 191)->unique();
            $table->string('event_type', 64)->index();
            $table->timestamp('event_timestamp')->nullable();
            $table->json('payload_summary')->nullable();
            $table->string('source', 32)->default('webhook');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_events');
    }
};
