<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookRequest extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'webhook_requests';

    // ULID is the PK; no auto-increment, no updated_at (capture-only).
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'endpoint_id',
        'method',
        'path',
        'query_string',
        'headers',
        'body',
        'body_size',
        'content_type',
        'ip_address',
        'user_agent',
        'received_at',
    ];

    protected $casts = [
        'headers'     => 'array',
        'body_size'   => 'integer',
        'received_at' => 'datetime',
    ];

    public function endpoint()
    {
        return $this->belongsTo(Endpoint::class);
    }

    /**
     * Parsed query parameters as an associative array.
     * For display only — the raw string is always stored.
     */
    public function getParsedQueryAttribute(): array
    {
        if (! $this->query_string) {
            return [];
        }
        parse_str($this->query_string, $params);
        return $params;
    }

    /**
     * Best-effort pretty-printed body for display.
     * The raw `body` field is always preserved unchanged.
     */
    public function getPrettyBodyAttribute(): ?string
    {
        if (! $this->body) {
            return null;
        }

        if (str_contains((string) $this->content_type, 'json')) {
            $decoded = json_decode($this->body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        }

        return $this->body;
    }
}
