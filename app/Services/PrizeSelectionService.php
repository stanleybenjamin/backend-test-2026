<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Prize;
use Illuminate\Database\Eloquent\Builder;

class PrizeSelectionService
{
    /**
     * Get all prizes that are playable (eligible by time/segment), regardless of daily limit.
     * This is important as decoys in the game should still be shown even if they can't be won anymore that day,
     * to maintain game balance and player engagement.
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
        return $this->eligiblePrizes($campaign, $segment)
            ->get()
            ->filter(fn (Prize $prize) => $this->hasRemainingDailyCapacity($prize))
            ->values();
    }

    public function eligiblePrizes(Campaign $campaign, string $segment): Builder
    {
        $now = now();

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
            })->addTodaysWins();
    }

    /**
     * Select a winning prize based on weighted randomness among eligible prizes that still have daily capacity.
     */
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

    /**
     * Check if a prize has remaining daily capacity.
     */
    public function hasRemainingDailyCapacity(Prize $prize): bool
    {
        if ($prize->daily_limit === null) {
            return true;
        }

        return $prize->wins_today < $prize->daily_limit;
    }
}
