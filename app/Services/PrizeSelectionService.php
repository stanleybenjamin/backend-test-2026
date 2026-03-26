<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Game;
use App\Models\Prize;
use Illuminate\Database\Eloquent\Builder;

class PrizeSelectionService
{
    /**
     * Get all prizes that are playable (eligible by time/segment), regardless of daily limit.
     */
    public function getPlayablePrizes(Campaign $campaign, string $segment)
    {
        return $this->eligiblePrizes($campaign, $segment)->get();
    }

    /**
     * Get all prizes that are winnable (eligible and under daily limit).
     */
    public function getWinnablePrizes(Campaign $campaign, string $segment)
    {
        $timezone = $campaign->timezone;

        return $this->eligiblePrizes($campaign, $segment)
            ->get()
            ->filter(fn (Prize $prize) => $this->hasRemainingDailyCapacity($prize, $timezone))
            ->values();
    }

    public function eligiblePrizes(Campaign $campaign, string $segment): Builder
    {
        $timezone = $campaign->timezone;
        $now = now($timezone);

        return Prize::query()
            ->where('campaign_id', $campaign->id)
            ->where('segment', $segment)
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            });
    }

    public function chooseWinningPrize(Campaign $campaign, string $segment): ?Prize
    {
        $eligible = $this->eligiblePrizes($campaign, $segment)
            ->get()
            ->filter(fn (Prize $prize) => $this->hasRemainingDailyCapacity($prize))
            ->pluck('id');

        if ($eligible->isEmpty()) {
            return null;
        }

        return Prize::query()
            ->whereIn('id', $eligible)
            ->orderByRaw('-LOG(RAND()) / weight')
            ->first();
    }

    public function hasRemainingDailyCapacity(Prize $prize, ?string $timezone = null): bool
    {
        if (! isset($prize->daily_limit) || $prize->daily_limit === null) {
            return true;
        }

        $timezone = $timezone ?? config('app.timezone');
        $today = now($timezone)->toDateString();

        $winsToday = Game::query()
            ->where('prize_id', $prize->id)
            ->whereDate('finished_at', $today)
            ->where('result', 'won')
            ->count();

        return $winsToday < $prize->daily_limit;
    }
}
