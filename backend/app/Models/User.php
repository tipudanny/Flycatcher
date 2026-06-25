<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'email',
        'password_hash',
        'plan',
        'status',
        'is_admin',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'is_admin'          => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    // Laravel's built-in auth expects `password`; map it to our column name.
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function endpoints()
    {
        return $this->hasMany(Endpoint::class, 'owner_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * A single limit value for this user's current plan.
     * Returns null when the plan grants unlimited (or the key is unknown).
     */
    public function planLimit(string $key): ?int
    {
        return config("plans.{$this->plan}.{$key}");
    }

    public function allowsCustomResponses(): bool
    {
        return (bool) config("plans.{$this->plan}.custom_responses", false);
    }
}
