<?php

namespace Tests\Unit\Services;

use App\Models\Campaign;
use App\Services\CampaignStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignStateServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    #[Test]
    public function campaign_not_started()
    {
        $campaign = Campaign::factory()->create([
            'starts_at' => now()->addDays(1),
        ]);

        $is_playable = app(CampaignStateService::class)->isPlayable($campaign);
        $this->assertFalse($is_playable);
        $this->assertStringContainsString('The campaign has not started yet', app(CampaignStateService::class)->messageFor($campaign));
    }

    #[Test]
    public function campaign_in_progress()
    {
        $campaign = Campaign::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $is_playable = app(CampaignStateService::class)->isPlayable($campaign);
        $this->assertTrue($is_playable);
    }

    #[Test]
    public function campaign_has_ended()
    {
        $campaign = Campaign::factory()->create([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $is_playable = app(CampaignStateService::class)->isPlayable($campaign);
        $this->assertFalse($is_playable);
        $this->assertStringContainsString('The campaign has ended', app(CampaignStateService::class)->messageFor($campaign));
    }
}
