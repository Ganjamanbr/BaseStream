<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StreamLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_token_id',
        'stream_id',
        'category',
        'quality',
        'resolved_url',
        'status',
        'response_time_ms',
        'client_ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'response_time_ms' => 'integer',
        ];
    }

    // ─── Relationships ───

    public function apiToken()
    {
        return $this->belongsTo(ApiToken::class);
    }

    // ─── Scopes ───

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'error');
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope para dashboard: últimos logs em ordem decrescente com token eager-loaded.
     */
    public function scopeForDashboard($query)
    {
        return $query->select('id', 'api_token_id', 'stream_id', 'quality', 'status', 'response_time_ms', 'client_ip', 'created_at')
                     ->with('apiToken:id,name,user_id')
                     ->where('created_at', '>', now()->subDays(7))
                     ->latest()
                     ->limit(500);
    }
}
