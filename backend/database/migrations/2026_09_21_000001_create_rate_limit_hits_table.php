<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per (limiter, key) pair, incremented on every 429 — not one
        // row per hit, so a sustained flood doesn't itself flood this table.
        Schema::create('rate_limit_hits', function (Blueprint $table) {
            $table->id();
            $table->string('limiter');           // e.g. 'ingest', 'api', 'auth'
            $table->string('key_type');          // ip | token | user
            $table->string('key_value');
            $table->string('route')->nullable();
            $table->unsignedInteger('hit_count')->default(1);
            $table->timestamp('first_hit_at');
            $table->timestamp('last_hit_at');
            $table->timestamps();

            $table->unique(['limiter', 'key_type', 'key_value']);
            $table->index(['key_type', 'key_value']);
            $table->index('last_hit_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_limit_hits');
    }
};
