<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('status', 24)->index(); // granted | revoked
            $table->string('origin', 100);
            $table->string('scope', 120);
            $table->string('policy_version', 64);
            $table->string('evidence_reference')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_events');
    }
};
