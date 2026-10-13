<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $attributes = ['active' => true, 'totp_enabled' => false];

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token', 'totp_secret'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'active' => 'boolean', 'totp_secret' => 'encrypted', 'totp_enabled' => 'boolean'];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function dealer()
    {
        return $this->hasOne(DealerProfile::class);
    }

    public function canManage(string $permission): bool
    {
        return $this->active && ($this->role?->name === 'super_admin' || in_array($permission, $this->role?->permissions ?? [], true));
    }

    protected static function booted(): void
    {
        static::creating(function ($u) {
            $u->role_id ??= Role::where('name', 'customer')->value('id');
        });
    }
}
