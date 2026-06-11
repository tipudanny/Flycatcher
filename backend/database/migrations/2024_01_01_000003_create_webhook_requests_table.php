<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_requests', function (Blueprint $table) {
            // ULID gives time-sortable IDs — natural chronological order,
            // efficient pagination, no separate created_at index needed for ordering.
            $table->ulid('id')->primary();

            // THE isolation boundary. Never query this table without this constraint.
            $table->uuid('endpoint_id')->index();

            $table->string('method', 10);
            $table->text('path')->nullable();            // sub-path after the token
            $table->text('query_string')->nullable();    // raw, unparsed

            // Full header map stored as JSON array-of-values per key (HTTP allows repeats).
            $table->json('headers');

            // Raw body as binary — webhooks can be binary (protobuf, gzip, multipart).
            // Never coerce to text; content_type is extracted separately for display only.
            $table->longText('body')->nullable();        // MySQL LONGTEXT; swap to LONGBLOB if truly binary
            $table->unsignedInteger('body_size')->default(0);
            $table->string('content_type')->nullable();  // extracted from headers for UI convenience only

            $table->string('ip_address', 45)->nullable(); // supports IPv6
            $table->text('user_agent')->nullable();

            $table->timestamp('received_at')->useCurrent();

            // Primary query: all requests for an endpoint, newest first
            $table->index(['endpoint_id', 'received_at']);

            $table->foreign('endpoint_id')->references('id')->on('endpoints')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_requests');
    }
};
