<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('google_place_id')->nullable()->unique();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('phone_normalized', 20)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('city', 120)->nullable()->index();
            $table->string('state', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('reviews_count')->default(0);
            $table->string('category', 120)->nullable();
            $table->string('business_status', 80)->nullable();
            $table->string('google_maps_url', 500)->nullable();
            $table->string('source', 30)->default('manual')->index();
            $table->string('status', 30)->default('new')->index();
            $table->string('duplicate_hash', 64)->nullable()->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('follow_up_at')->nullable()->index();
            $table->timestamp('last_contacted_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scrape_job_id')->nullable()->constrained('scrape_jobs')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_to', 'status']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
