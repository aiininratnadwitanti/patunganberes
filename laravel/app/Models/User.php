<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar_url',
        'preferred_currency',
        'default_tax_rate',
        'default_service_rate',
        'default_rounding',
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
            'default_tax_rate' => 'integer',
            'default_service_rate' => 'integer',
        ];
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class)->orderByDesc('is_default')->orderBy('id');
    }

    public function splitBills(): HasMany
    {
        return $this->hasMany(SplitBill::class)->latest('created_at');
    }

    public function iuranPools(): HasMany
    {
        return $this->hasMany(IuranPool::class)->latest('created_at');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class)->latest('created_at');
    }

    public function defaultBankAccount(): ?BankAccount
    {
        return $this->bankAccounts()->first();
    }

    public function getFirstNameAttribute(): string
    {
        return explode(' ', trim($this->name))[0] ?? $this->name;
    }

    public function getAvatarAttribute(): string
    {
        return $this->avatar_url
            ?: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop&q=80';
    }
}
