<?php

namespace App\Services;

use App\Models\Campaign;

class CampaignStateService
{
    public function messageFor(Campaign $campaign, ?string $segment = null): ?string
    {
        if (! $this->hasStarted($campaign)) {
            return 'The campaign has not started yet.';
        }

        if ($this->hasEnded($campaign)) {
            return 'The campaign has ended.';
        }

        if ($segment && ! in_array($segment, ['low', 'med', 'high'])) {
            return 'Invalid segment';
        }

        return null;
    }

    public function hasStarted(Campaign $campaign): bool
    {
        if (! $campaign->starts_at) {
            return true; // If no start date is set, consider it as started
        }

        // Compare UTC to UTC
        return now()->greaterThanOrEqualTo($campaign->starts_at);
    }

    public function hasEnded(Campaign $campaign): bool
    {
        if (! $campaign->ends_at) {
            return false; // If no end date is set, consider it as not ended
        }

        // Compare UTC to UTC
        return now()->greaterThanOrEqualTo($campaign->ends_at);
    }

    public function isPlayable(Campaign $campaign, ?string $segment = null): bool
    {
        return $this->hasStarted($campaign) && ! $this->hasEnded($campaign) && (! $segment || in_array($segment, ['low', 'med', 'high']));
    }
}
