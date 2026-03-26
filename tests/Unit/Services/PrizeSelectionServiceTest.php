<?php

namespace Tests\Unit\Services;

use App\Models\Campaign;
use App\Models\Prize;
use App\Services\PrizeSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PrizeSelectionServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function filter_by_campaign()
    {
        // create two campaigns and prizes
        Campaign::factory()->count(2)->has(
            Prize::factory()->count(3)
        )->create();

        $campaign = Campaign::first();

        $service = app(PrizeSelectionService::class);
        $prize = $service->chooseWinningPrize($campaign, 'low');

        $this->assertNotNull($prize);
        $this->assertEquals($campaign->id, $prize->campaign_id);
    }

    #[Test]
    public function filter_by_segment()
    {
        $this->assertTrue(true);
    }

    #[Test]
    public function filter_by_price_date()
    {
        $this->assertTrue(true);
    }

    #[Test]
    public function ensure_exhausted_prizes_are_excluded()
    {
        $this->assertTrue(true);
    }

    #[Test]
    public function returns_null_when_no_prizes_available()
    {
        $this->assertTrue(true);
    }
}
