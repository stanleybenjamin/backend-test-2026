<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prize extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'daily_limit',
        'description',
        'segment',
        'weight',
        'image',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public static function search($query)
    {
        return empty($query) ? static::query()
            : static::where('name', 'like', '%'.$query.'%');
    }

    public function games()
    {
        return $this->hasMany(Game::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function scopeAddTodaysWins(Builder $query)
    {
        return $query->withCount(['games as wins_today' => function ($q) {
            $q->whereDate('created_at', today())
                ->whereNotNull('prize_id');
        }]);
    }

    public function scopeForSegment($query, string $segment)
    {
        return $query->where('segment', $segment);
    }

    public function scopeForCampaign($query, int $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }
}
