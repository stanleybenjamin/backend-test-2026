<?php

namespace Tests\Unit\Services;

use App\Models\Campaign;
use App\Models\Game;
use App\Models\Prize;
use App\Services\GameplayService;
use App\Services\GameplaySessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameplayServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function can_flip_tile()
    {
        $campaign = Campaign::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        Prize::factory()->count(5)->create([
            'campaign_id' => $campaign->id,
            'segment' => 'low',
            'starts_at' => $campaign->starts_at,
            'ends_at' => $campaign->ends_at,
        ]);

        // generate a game with plan
        $game = app(GameplaySessionService::class)->findOrCreateNewGame($campaign, 'low', 'player_token');

        app(GameplayService::class)->flip($game, 1);

        $this->assertDatabaseHas('game_tiles', [
            'game_id' => $game->id,
            'tile_index' => 1,
        ]);
    }

    #[Test]
    public function duplication_tile_index_are_not_allowed()
    {
        $campaign = Campaign::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        Prize::factory()->count(5)->create([
            'campaign_id' => $campaign->id,
            'segment' => 'low',
            'starts_at' => $campaign->starts_at,
            'ends_at' => $campaign->ends_at,
        ]);

        // generate a game with plan
        $game = app(GameplaySessionService::class)->findOrCreateNewGame($campaign, 'low', 'player_token');

        $result1 = app(GameplayService::class)->flip($game, 1);
        $tilesCountBefore = $game->tiles()->count();
        $result2 = app(GameplayService::class)->flip($game->fresh(), 1);
        $tilesCountAfter = $game->tiles()->count();

        // The number of tiles should not increase
        $this->assertEquals($tilesCountBefore, $tilesCountAfter);
        // The image should be the same
        $this->assertEquals($result1['tileImage'], $result2['tileImage']);
    }

    #[Test]
    public function same_game_cannot_accept_flip_after_completion()
    {
        $campaign = Campaign::factory()->create([
            'timezone' => 'UTC',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        Prize::factory()->count(5)->create([
            'campaign_id' => $campaign->id,
            'segment' => 'low',
            'starts_at' => $campaign->starts_at,
            'ends_at' => $campaign->ends_at,
        ]);

        // generate a game with plan
        $game = app(GameplaySessionService::class)->findOrCreateNewGame($campaign, 'low', 'player_token');

        // mark the game as finished
        $game->finished_at = now();
        $game->save();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This game is already finished.');

        app(GameplayService::class)->flip($game, 1);
    }

    #[Test]
    public function third_match_ends_the_game_with_win()
    {
        $campaign = Campaign::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $prizes = Prize::factory()->count(3)->create([
            'campaign_id' => $campaign->id,
            'segment' => 'low',
            'starts_at' => $campaign->starts_at,
            'ends_at' => $campaign->ends_at,
        ]);

        // generate a game with a winning flip at 3
        $game = app(GameplaySessionService::class)->findOrCreateNewGame($campaign, 'low', 'player_token');
        $game->winning_flip = 3;
        $game->winning_prize_id = $prizes[0]->id;
        $game->max_flips = 3;
        $game->reveal_plan = [$prizes[0]->id, $prizes[1]->id, $prizes[0]->id];
        $game->save();

        // Flip 1 (not win)
        app(GameplayService::class)->flip($game, 1);
        $game->refresh();
        $this->assertNull($game->finished_at);

        // Flip 2 (not win)
        app(GameplayService::class)->flip($game, 2);
        $game->refresh();
        $this->assertNull($game->finished_at);

        // Flip 3 (should win)
        $result = app(GameplayService::class)->flip($game, 3);
        $game->refresh();
        $this->assertEquals('won', $game->result);
        $this->assertNotNull($game->finished_at);
        $this->assertEquals('You won a prize!', $result['message'] ?? null);
    }

    #[Test]
    public function max_flips_ends_the_game_with_loss()
    {
        $campaign = Campaign::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $prizes = Prize::factory()->count(3)->create([
            'campaign_id' => $campaign->id,
            'segment' => 'low',
            'starts_at' => $campaign->starts_at,
            'ends_at' => $campaign->ends_at,
        ]);

        // generate a game with no winning flip
        $game = app(GameplaySessionService::class)->findOrCreateNewGame($campaign, 'low', 'player_token');
        $game->winning_flip = null;
        $game->winning_prize_id = null;
        $game->max_flips = 3;
        $game->reveal_plan = [$prizes[0]->id, $prizes[1]->id, $prizes[2]->id];
        $game->save();

        // Flip 1
        app(GameplayService::class)->flip($game, 1);
        $game->refresh();
        $this->assertNull($game->finished_at);

        // Flip 2
        app(GameplayService::class)->flip($game, 2);
        $game->refresh();
        $this->assertNull($game->finished_at);

        // Flip 3 (should lose)
        $result = app(GameplayService::class)->flip($game, 3);
        $game->refresh();
        $this->assertEquals('lost', $game->result);
        $this->assertNotNull($game->finished_at);
        $this->assertEquals('You lost!', $result['message'] ?? null);
    }

    #[Test]
    public function reveal_tile_never_returns_prize_that_is_exhausted()
    {
        $campaign = Campaign::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        // Create three prizes, only the first has daily_limit 1
        $prizes = Prize::factory()->count(3)->create([
            'campaign_id' => $campaign->id,
            'segment' => 'low',
            'starts_at' => $campaign->starts_at,
            'ends_at' => $campaign->ends_at,
            'daily_limit' => 0,
        ]);
        $exhaustiblePrize = $prizes[0];
        $exhaustiblePrize->daily_limit = 1;
        $exhaustiblePrize->save();

        // Exhaust the prize by marking a win
        $game1 = app(GameplaySessionService::class)->findOrCreateNewGame($campaign, 'low', 'player_token1');
        $game1->winning_flip = 1;
        $game1->winning_prize_id = $exhaustiblePrize->id;
        $game1->max_flips = 1;
        $game1->reveal_plan = [$exhaustiblePrize->id];
        $game1->save();

        app(GameplayService::class)->flip($game1, 1);

        // Now create a new game and try to win the same prize
        $game2 = app(GameplaySessionService::class)->findOrCreateNewGame($campaign, 'low', 'player_token2');
        $game2->winning_flip = 1;
        $game2->winning_prize_id = $exhaustiblePrize->id;
        $game2->max_flips = 1;
        $game2->reveal_plan = [$exhaustiblePrize->id];
        $game2->save();

        $result = app(GameplayService::class)->flip($game2, 1);
        $game2->refresh();
        // Should lose, not win, because prize is exhausted
        $this->assertEquals('lost', $game2->result);
        $this->assertNotEquals('You won a prize!', $result['message'] ?? null);
    }
}
