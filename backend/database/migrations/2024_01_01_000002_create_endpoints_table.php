<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The public path segment — high-entropy, used in capture & view URLs.
            // NEVER expose the internal `id` in URLs; always route by `token`.
            $table->string('token', 32)->unique();

            // Ownership: one of these will be set, the other null.
            // NULL owner_user_id = guest-owned; populated = claimed by a user.
            $table->uuid('owner_user_id')->nullable()->index();
            $table->string('guest_session_id', 64)->nullable()->index();

            $table->string('label')->nullable();

            // Per-endpoint custom response config (status code, body, headers, delay)
            $table->json('settings')->nullable(); // MySQL 8 forbids defaults on JSON columns

            // Denormalized for cheap dashboard queries — updated asynchronously.
            $table->unsignedBigInteger('request_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();

            // Retention: null = no expiry
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('endpoints');
    }
};
