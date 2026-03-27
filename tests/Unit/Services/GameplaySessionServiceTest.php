<?php

namespace Tests\Unit\Services;

use App\Models\Campaign;
use App\Models\Prize;
use App\Services\GameplaySessionService;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class GameplaySessionServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function start_a_new_game_when_no_active_game_exists()
    {
        // seed database with campaign, prizes, and segments as needed
        /**
         * @var Campaign
         */
        $campaign = Campaign::factory()->create([
            'timezone' => 'UTC',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        Prize::factory()->count(5)->state($this->segmentSequence())->create([
            'starts_at' => now(),
            'segment' => 'low',
            'ends_at' => now()->addDay(),
            'campaign_id' => $campaign->id,
        ]);

        $result = app(GameplaySessionService::class)->findOrCreateNewGame($campaign, 'low', Str::uuid()->toString());

        $this->assertNotNull($result);
        $this->assertEquals('account', $result->account);
    }

    #[Test]
    public function restore_existing_game_when_active_game_exists()
    {
        // create Campaign
        $campaign = Campaign::factory()->create([
            'timezone' => 'UTC',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        Prize::factory()->count(5)->state($this->segmentSequence())->create([
            'campaign_id' => $campaign->id,
            'segment' => 'low',
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
        ]);

        $playerToken = Str::uuid()->toString();

        // Create an active game for the player
        $activeGame = $campaign->games()->create([
            'account' => 'account',
            'player_token' => $playerToken,
            'finished_at' => null,
            'segment' => 'low',
        ]);

        $result = app(GameplaySessionService::class)->findOrCreateNewGame($campaign, 'low', $playerToken);
        $this->assertNotNull($result);
        $this->assertTrue($activeGame->is($result));
    }

    #[Test]
    public function does_not_restore_finished_game()
    {
        // create Campaign
        $campaign = Campaign::factory()->create([
            'timezone' => 'UTC',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        Prize::factory()->count(5)->state($this->segmentSequence())->create([
            'campaign_id' => $campaign->id,
            'starts_at' => now(),
            'segment' => 'low',
            'ends_at' => now()->addDay(),
        ]);

        $playerToken = Str::uuid()->toString();

        // Create a finished game for the player
        $finishedGame = $campaign->games()->create([
            'account' => 'account',
            'player_token' => $playerToken,
            'finished_at' => now(),
            'segment' => 'low',
        ]);

        $result = app(GameplaySessionService::class)->findOrCreateNewGame($campaign, 'low', $playerToken);
        $this->assertNotNull($result);
        $this->assertFalse($finishedGame->is($result));
    }

    #[Test]
    public function create_new_game_when_existing_game_is_finished()
    {
        // create Campaign
        $campaign = Campaign::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        Prize::factory()->count(5)->state($this->segmentSequence())->create([
            'campaign_id' => $campaign->id,
            'segment' => 'low',
            'starts_at' => $campaign->starts_at,
            'ends_at' => $campaign->ends_at,
        ]);

        $playerToken = Str::uuid()->toString();

        // Create a finished game for the player
        $finishedGame = $campaign->games()->create([
            'account' => 'account',
            'player_token' => $playerToken,
            'finished_at' => now(),
            'segment' => 'low',
        ]);

        $result = app(GameplaySessionService::class)->findOrCreateNewGame($campaign, 'low', $playerToken);
        $this->assertNotNull($result);
        $this->assertFalse($finishedGame->is($result));
        // check game count for player is 2
        $this->assertEquals(2, $campaign->games()->where('account', 'account')->count());
        $this->assertEquals('account', $result->account);
    }

    #[Test]
    public function cannot_create_game_when_no_prizes_for_segment()
    {
        // create Campaign
        $campaign = Campaign::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        // Don't create any prizes for the 'low' segment
        $playerToken = Str::uuid()->toString();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough playable prizes to build a game.');
        app(GameplaySessionService::class)->findOrCreateNewGame($campaign, 'low', $playerToken);
    }

    protected function segmentSequence()
    {
        return new Sequence(
            ['segment' => 'low'],
            ['segment' => 'med'],
            ['segment' => 'high']
        );
    }
}
