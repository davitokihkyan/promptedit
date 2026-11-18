<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nanodollars, net of what running generations hold.
            $table->bigInteger('balance')->default(0);
            $table->boolean('is_admin')->default(false);
        });

        Schema::create('credit_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount'); // nanodollars
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_purchases');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['balance', 'is_admin']);
        });
    }
};
