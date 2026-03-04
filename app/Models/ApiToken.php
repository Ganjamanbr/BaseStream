<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'token',
        'last_ip',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ───

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function streamLogs()
    {
        return $this->hasMany(StreamLog::class);
    }

    // ─── Helpers ───

    public static function generateToken(): string
    {
        return 'bs_' . Str::random(40);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function touch($attribute = null): bool
    {
        $this->last_used_at = now();
        return $this->save();
    }
}
