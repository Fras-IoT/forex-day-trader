<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public static function ensureDefaultAdmin(): void
    {
        if (static::count() === 0) {
            static::create([
                'name' => 'Master Trader',
                'email' => 'admin@forex.local',
                'password' => Hash::make('admin12345'),
                'role' => 'admin',
                'is_active' => true,
            ]);
        }
    }
}
