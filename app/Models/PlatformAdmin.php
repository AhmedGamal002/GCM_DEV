<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Manages the product itself, across every tenant. Deliberately separate
 * from User: no tenant_id, no BelongsToTenant, no Spatie roles (all
 * platform admins are equal), authenticated via its own `platform` guard.
 */
class PlatformAdmin extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
