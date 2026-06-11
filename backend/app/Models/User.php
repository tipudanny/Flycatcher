<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'email',
        'password_hash',
        'plan',
        'status',
    ];

    protected $hidden = [
        'password_hash',
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
}
