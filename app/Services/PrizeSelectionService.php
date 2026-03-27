<?php

namespace App\Services;

use App\Models\Campaign;
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
            })->withCount(['games as wins_today' => function (Builder $query) use ($timezone) {
                $today = now($timezone)->toDateString();

                $query->whereDate('finished_at', $today);
            }]);
    }

    public function chooseWinningPrize(Campaign $campaign, string $segment): ?Prize
    {
        $eligible = $this->eligiblePrizes($campaign, $segment)
            ->get()
            ->filter(fn (Prize $prize) => $this->hasRemainingDailyCapacity($prize, $campaign->timezone))
            ->pluck('id');

        if ($eligible->isEmpty()) {
            return null;
        }

        return Prize::query()
            ->whereIn('id', $eligible)
            ->orderByRaw('-LOG(RAND()) / weight')
            ->first();
    }

    public function hasRemainingDailyCapacity(Prize $prize, string $timezone): bool
    {
        if ($prize->daily_limit === null) {
            return true;
        }

        return $prize->wins_today < $prize->daily_limit;
    }
}
