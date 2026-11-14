<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone_e164', 24)->unique();
            $table->string('origin', 100)->default('cadastro_manual');
            $table->json('interests')->nullable();
            $table->string('interests_text')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('default_group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('pipeline_stage', 40)->default('novo')->index();
            $table->string('consent_status', 24)->default('pendente')->index();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_invited_at')->nullable();
            $table->timestamp('joined_confirmed_at')->nullable();
            $table->foreignId('joined_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('record_source', 24)->default('real')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
