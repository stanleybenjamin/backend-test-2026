<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Game;
use App\Models\Prize;
use Illuminate\Http\Request;
use Illuminate\Support\Lottery;
use Illuminate\Support\Str;
use RuntimeException;

class GameplaySessionService
{
    protected $config = [
        'winning_odds' => [6, 10], // 60% chance to win
        'use_lottery' => true,
    ];

    public function __construct(
        protected CampaignStateService $campaignStateService,
        protected PrizeSelectionService $prizeSelectionService,
    ) {}

    public function resolvePlayerToken(Request $request): string
    {
        if ($request->session()->has('player_token')) {
            return $request->session()->get('player_token');
        }

        $token = (string) Str::uuid();
        $request->session()->put('player_token', $token);

        return $token;
    }

    /**
     * Finds the active game for the given player and campaign segment, or creates a new one if none exists.
     *
     * If the campaign is not playable or there are not enough prizes, throws an exception.
     * If a game is already active for the player, returns it. Otherwise, creates a new game with a random win/loss plan.
     *
     * Techniques used:
     * Here is where we determin if a user would win or loose and on which flip, then we build a reveal plan accordingly.
     * The plan is an array of prize IDs for each flip, with the winning prize placed according to the determined winning flip,
     * and decoys filling the other positions.
     * We also ensure that no prize appears more than twice in the plan to maintain game balance.
     *
     * @param  Campaign  $campaign  The campaign to play in.
     * @param  string  $segment  The segment (e.g. 'low', 'med', 'high').
     * @param  string  $playerToken  The unique player token (from session).
     * @return Game The active or newly created game instance.
     *
     * @throws RuntimeException If campaign is not playable or not enough prizes.
     */
    public function findOrCreateNewGame(Campaign $campaign, string $segment, string $playerToken): Game
    {
        if (! $this->campaignStateService->isPlayable($campaign, $segment)) {
            throw new RuntimeException(
                $this->campaignStateService->messageFor($campaign, $segment) ?? 'Campaign is not playable.'
            );
        }

        $activeGame = $campaign->games()
            ->where('player_token', $playerToken)
            ->whereNull('finished_at')
            ->first();

        if ($activeGame) {
            return $activeGame;
        }

        $playablePrizes = $this->prizeSelectionService
            ->getPlayablePrizes($campaign, $segment)
            ->values();

        if ($playablePrizes->count() < 3) {
            throw new RuntimeException('Not enough playable prizes to build a game.');
        }

        $maxFlips = 5;

        $winningPrize = null;

        if ($this->config['use_lottery']) {
            $winningPrize = Lottery::odds(...$this->config['winning_odds'])
                ->winner(fn () => $this->prizeSelectionService->chooseWinningPrize($campaign, $segment))
                ->loser(fn () => null)
                ->choose();
        } else {
            $winningPrize = $this->prizeSelectionService->chooseWinningPrize($campaign, $segment);
        }

        $winningFlip = null;
        $revealPlan = [];

        if ($winningPrize) {
            $winningFlip = random_int(3, $maxFlips);
            $revealPlan = $this->buildWinningPlan(
                $playablePrizes,
                $winningPrize,
                $winningFlip,
                $maxFlips,
            );
        } else {
            $revealPlan = $this->buildLosingPlan($playablePrizes, $maxFlips);
        }

        return $campaign->games()->create([
            'account' => 'account',
            'player_token' => $playerToken,
            'segment' => $segment,
            'winning_prize_id' => $winningPrize?->id,
            'winning_flip' => $winningFlip,
            'max_flips' => $maxFlips,
            'flips_count' => 0,
            'reveal_plan' => $revealPlan,
        ]);
    }

    /**
     * Summary of buildWinningPlan
     * Generate a list of prize IDs for each flip, ensuring the winning prize appears at
     * the predetermined winning flip and is not repeated more than twice,
     * while filling other positions with decoy prizes.
     *
     * @param  mixed  $playablePrizes
     *
     * @throws RuntimeException
     */
    protected function buildWinningPlan($playablePrizes, Prize $winningPrize, int $winningFlip, int $maxFlips): array
    {
        $decoys = $playablePrizes
            ->where('id', '!=', $winningPrize->id)
            ->values();

        if ($decoys->count() < 2) {
            throw new RuntimeException('Not enough decoys to build a winning plan.');
        }

        $plan = array_fill(0, $maxFlips, null);

        $availableBeforeWin = range(0, $winningFlip - 2);
        shuffle($availableBeforeWin);

        $winnerPositions = array_slice($availableBeforeWin, 0, 2);
        $winnerPositions[] = $winningFlip - 1;

        sort($winnerPositions);

        foreach ($winnerPositions as $position) {
            $plan[$position] = $winningPrize->id;
        }

        for ($i = 0; $i < $maxFlips; $i++) {
            if ($plan[$i] !== null) {
                continue;
            }

            $safeDecoys = $decoys->filter(function (Prize $prize) use ($plan) {
                $count = count(array_filter($plan, fn ($id) => $id === $prize->id));

                return $count < 2;
            })->values();

            if ($safeDecoys->isEmpty()) {
                throw new RuntimeException('Unable to complete winning plan safely.');
            }

            $plan[$i] = $safeDecoys->random()->id;
        }

        return $plan;
    }

    /**
     * Generate a list of prize IDs for each flip, ensuring no prize appears more than twice.
     * Ultimately will lead to a loss since there is no winning prize in the plan,
     * but maintains game balance by limiting prize repetition.
     *
     * @param  mixed  $playablePrizes
     *
     * @throws RuntimeException
     */
    protected function buildLosingPlan($playablePrizes, int $maxFlips): array
    {
        $plan = [];

        for ($i = 0; $i < $maxFlips; $i++) {
            $safePrizes = $playablePrizes->filter(function (Prize $prize) use ($plan) {
                $count = count(array_filter($plan, fn ($id) => $id === $prize->id));

                return $count < 2;
            })->values();

            if ($safePrizes->isEmpty()) {
                throw new RuntimeException('Unable to build a losing plan safely.');
            }

            $plan[] = $safePrizes->random()->id;
        }

        return $plan;
    }

    protected function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
}
