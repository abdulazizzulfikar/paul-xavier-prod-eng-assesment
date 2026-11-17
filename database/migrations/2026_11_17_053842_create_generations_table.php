<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // All money columns are integer nanodollars (1 USD = 1,000,000,000).
        Schema::create('generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pixelforge_id')->nullable()->unique();
            $table->string('status')->index();

            $table->text('prompt');
            $table->string('size');
            $table->string('quality');
            $table->json('reference_images');

            $table->unsignedBigInteger('estimated_min_nano');
            $table->unsignedBigInteger('estimated_max_nano');

            $table->json('rates')->nullable();
            $table->json('usage')->nullable();
            $table->unsignedBigInteger('provider_cost_nano')->nullable();
            $table->unsignedBigInteger('charged_nano')->nullable();

            $table->text('image_url')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
