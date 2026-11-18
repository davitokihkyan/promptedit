<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            // Also sent as the Idempotency-Key and as metadata.generation_id.
            $table->uuid('id')->primary();
            // Billing records outlive deleted accounts.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pixelforge_id')->nullable()->unique();
            $table->string('status')->index();

            $table->text('prompt');
            $table->string('size');
            $table->string('quality');
            $table->json('reference_images');

            // Money is stored in whole nanodollars (1e-9 USD).
            $table->bigInteger('estimated_charge');
            $table->bigInteger('max_charge');
            $table->bigInteger('provider_cost')->nullable();
            $table->bigInteger('charge')->nullable();

            // What Pixelforge billed for, so every charge can be audited and recomputed.
            $table->json('rates')->nullable();
            $table->unsignedInteger('input_text_tokens')->nullable();
            $table->unsignedInteger('input_image_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('submit_attempts')->default(0); // failed submissions, drives the backoff
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
