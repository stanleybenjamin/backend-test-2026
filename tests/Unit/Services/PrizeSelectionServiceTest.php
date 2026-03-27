<?php

namespace Tests\Unit\Services;

use App\Models\Campaign;
use App\Models\Prize;
use App\Services\PrizeSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrizeSelectionServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    #[Test]
    public function filter_by_campaign()
    {
        // $this->freezeTime();
        // create two campaigns and prizes
        Campaign::factory()->count(2)->has(
            Prize::factory()->count(3)->afterCreating(function (Prize $prize) {
                $prize->starts_at = now();
                $prize->segment = 'low';
                $prize->ends_at = now()->addDays(1);
                $prize->save();
            })
        )->create([
            'timezone' => 'UTC',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(2),
        ]);

        $campaign = Campaign::first();

        // dump($campaign->prizes);

        $service = app(PrizeSelectionService::class);
        $prize = $service->chooseWinningPrize($campaign, 'low');

        $this->assertNotNull($prize);
        $this->assertEquals($campaign->id, $prize->campaign_id);
    }

    #[Test]
    public function filter_by_segment()
    {
        $campaign = Campaign::factory()->create([
            'timezone' => 'UTC',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        Prize::factory()->create(['campaign_id' => $campaign->id, 'segment' => 'low', 'starts_at' => $campaign->starts_at, 'ends_at' => $campaign->ends_at]);
        Prize::factory()->create(['campaign_id' => $campaign->id, 'segment' => 'high', 'starts_at' => $campaign->starts_at, 'ends_at' => $campaign->ends_at]);

        $service = app(PrizeSelectionService::class);
        $prizes = $service->getPlayablePrizes($campaign, 'low');

        $this->assertCount(1, $prizes);
        $this->assertEquals('low', $prizes->first()->segment);
    }

    #[Test]
    public function filter_by_prize_date()
    {
        $campaign = Campaign::factory()->create([
            'timezone' => 'UTC',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(2),
        ]);
        $now = now();
        Prize::factory()->create([
            'campaign_id' => $campaign->id,
            'segment' => 'low',
            'starts_at' => $now->copy()->subDay(),
            'ends_at' => $now->copy()->addDay(),
        ]);
        Prize::factory()->create([
            'campaign_id' => $campaign->id,
            'segment' => 'low',
            'starts_at' => $now->copy()->addDay(), // not started yet
            'ends_at' => $now->copy()->addDays(2),
        ]);

        $service = app(PrizeSelectionService::class);
        $prizes = $service->getPlayablePrizes($campaign, 'low');

        $this->assertCount(1, $prizes);
        $this->assertTrue($prizes->first()->starts_at->lessThanOrEqualTo($now));
    }

    #[Test]
    public function ensure_exhausted_prizes_are_excluded()
    {
        $campaign = Campaign::factory()->create([
            'timezone' => 'UTC',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(2),
        ]);

        $prize = Prize::factory()->create([
            'campaign_id' => $campaign->id,
            'segment' => 'low',
            'starts_at' => now(),
            'ends_at' => now()->addDays(1),
            'daily_limit' => 1,
        ]);

        // Simulate a win today
        $prize->games()->create([
            'campaign_id' => $campaign->id,
            'prize_id' => $prize->id,
            'segment' => $prize->segment,
            'result' => 'won',
            'account' => 'account',
            'finished_at' => now(),
        ]);

        $service = app(PrizeSelectionService::class);
        $winnable = $service->getWinnablePrizes($campaign, 'low');
        $this->assertCount(0, $winnable);
    }

    #[Test]
    public function returns_null_when_no_prizes_available()
    {
        $campaign = Campaign::factory()->create(['timezone' => 'UTC']);
        $service = app(PrizeSelectionService::class);
        $prize = $service->chooseWinningPrize($campaign, 'low');
        $this->assertNull($prize);
    }
}
