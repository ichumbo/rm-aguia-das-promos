<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('display_name');
            $table->string('category', 32)->default('MARKETING');
            $table->string('language', 16)->default('pt_BR');
            $table->string('status', 32)->default('APPROVED')->index();
            $table->text('body_text');
            $table->json('variables')->nullable();
            $table->string('meta_template_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
