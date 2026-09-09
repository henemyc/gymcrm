<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrape_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query')->nullable();
            $table->string('location_label')->nullable();
            $table->string('place_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius')->nullable();
            $table->string('keyword', 80)->nullable();
            $table->string('type', 40)->nullable();
            $table->string('mode', 20)->default('nearby');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('results_found')->default(0);
            $table->unsignedInteger('saved_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('assigned_count')->default(0);
            $table->string('error_message', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_jobs');
    }
};
