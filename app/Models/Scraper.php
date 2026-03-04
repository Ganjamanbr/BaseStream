<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scraper extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'base_url',
        'is_active',
        'category',
        'sites_config',
        'success_count',
        'failure_count',
        'last_check_at',
    ];

    protected function casts(): array
    {
        return [
            'sites_config' => 'array',
            'is_active' => 'boolean',
            'last_check_at' => 'datetime',
        ];
    }

    // ─── Helpers ───

    public function successRate(): float
    {
        $total = $this->success_count + $this->failure_count;
        if ($total === 0) return 0;
        return round(($this->success_count / $total) * 100, 2);
    }

    public function incrementSuccess(): void
    {
        $this->increment('success_count');
        $this->update(['last_check_at' => now()]);
    }

    public function incrementFailure(): void
    {
        $this->increment('failure_count');
        $this->update(['last_check_at' => now()]);
    }
}
