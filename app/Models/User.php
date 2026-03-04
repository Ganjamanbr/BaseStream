<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'stripe_id',
        'tier',
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
        ];
    }

    // ─── Relationships ───

    public function apiTokens()
    {
        return $this->hasMany(ApiToken::class);
    }

    public function streamLogs()
    {
        return $this->hasManyThrough(StreamLog::class, ApiToken::class);
    }

    // ─── Helpers ───

    public function isPro(): bool
    {
        return $this->tier === 'pro';
    }

    public function maxTokens(): int
    {
        return config("streams.tiers.{$this->tier}.max_tokens", 2);
    }

    public function canAccessCategory(string $category): bool
    {
        $allowed = config("streams.tiers.{$this->tier}.categories", []);
        return in_array($category, $allowed);
    }
}
