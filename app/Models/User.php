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
        'xtream_username',
        'xtream_password',
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

    // ─── Xtream Codes helpers ───

    public function hasXtreamCredentials(): bool
    {
        return !empty($this->xtream_username) && !empty($this->xtream_password);
    }

    /** Hash a plain-text password for storage. */
    public static function hashXtreamPassword(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** Verify a plain-text password against the stored hash. */
    public function verifyXtreamPassword(string $plain): bool
    {
        return hash_equals((string) $this->xtream_password, hash('sha256', $plain));
    }

    /** Find user by Xtream username + verify password in one call. */
    public static function findByXtreamCredentials(string $username, string $password): ?self
    {
        $user = static::where('xtream_username', $username)->first();
        if (!$user) return null;
        return $user->verifyXtreamPassword($password) ? $user : null;
    }

    /** Generate a unique Xtream username for this user. */
    public static function generateXtreamUsername(): string
    {
        do {
            $username = 'bs_' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
        } while (static::where('xtream_username', $username)->exists());
        return $username;
    }
}
