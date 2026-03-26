<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Lottery;
use Illuminate\Support\Str;

class GameplaySessionService
{
    public function resolvePlayerToken(Request $request): ?string
    {
        // get token from session, if it exists else create a new one and store it in the session
        if ($request->session()->has('player_token')) {
            return $request->session()->get('player_token');
        } else {
            $token = Str::uuid()->toString(); // Generate a random token
            $request->session()->put('player_token', $token);

            return $token;
        }
    }

    public function findOrCreateNewGame(Campaign $campaign, string $segment, string $playerToken): Game
    {
        if (! app(CampaignStateService::class)->isPlayable($campaign)) {
            throw new \Exception(app(CampaignStateService::class)->messageFor($campaign));
        }

        // Check if there's an active game for this player in the campaign
        $activeGame = $campaign->games()
            ->where('player_token', $playerToken)
            ->whereNull('finished_at')
            ->first();

        if ($activeGame) {
            return $activeGame;
        }

        // user Lottery
        $winningPrize = Lottery::odds(7, 10)
            ->winner(fn () => app(PrizeSelectionService::class)->chooseWinningPrize($campaign, $segment)) // 70% chance to win
            ->loser(fn () => null) // 30% chance to lose
            ->choose();

        // If no active game, create a new one
        return $campaign->games()->create([
            'account' => Str::random(8),
            'player_token' => $playerToken,
            'winning_prize_id' => optional($winningPrize)->id, // choose
            'winning_flip' => random_int(3, 5), // user can win on 3rd, 4th, or 5th flip
            'segment' => $segment,
        ]);
    }
}
