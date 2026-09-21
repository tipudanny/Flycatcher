<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manual block list an admin manages from the rate-limit activity
        // view — separate from a user's active/suspended status, since IPs
        // and endpoint tokens don't have one of those.
        Schema::create('blocked_entities', function (Blueprint $table) {
            $table->id();
            $table->string('type');   // ip | token
            $table->string('value');
            $table->boolean('is_locked')->default(true);
            $table->string('reason')->nullable();
            $table->foreignUuid('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_entities');
    }
};
