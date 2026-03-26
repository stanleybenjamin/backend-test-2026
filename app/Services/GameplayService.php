<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameTile;
use App\Models\Prize;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GamePlayService
{
    public function __construct(
        protected PrizeSelectionService $prizeSelectionService,
    ) {}

    public function flip(Game $game, int $tileIndex): array
    {
        return DB::transaction(function () use ($game, $tileIndex) {
            $game->loadMissing(['campaign', 'tiles.prize', 'winningPrize']);

            $this->ensureGameCanBePlayed($game);
            $this->ensureTileIndexIsValid($tileIndex);
            $this->ensureTileNotAlreadyRevealed($game, $tileIndex);

            $prize = $this->chooseRevealPrize($game);

            GameTile::create([
                'game_id' => $game->id,
                'tile_index' => $tileIndex,
                'prize_id' => $prize->id,
            ]);

            $game->increment('flips_count');
            $game->refresh()->load(['tiles.prize', 'winningPrize']);

            $matchCount = $game->tiles->where('prize_id', $prize->id)->count();

            if ($matchCount >= 3) {
                return $this->finishAsWin($game, $prize);
            }

            if ($game->flips_count >= $game->max_flips) {
                return $this->finishAsLoss($game, $prize);
            }

            return [
                'tileImage' => $prize->image,
            ];
        });
    }

    protected function ensureGameCanBePlayed(Game $game): void
    {
        if ($game->finished_at !== null) {
            throw new RuntimeException('This game is already finished.');
        }

        if ($game->winning_prize_id === null) {
            throw new RuntimeException('This game has no available winning prize.');
        }
    }

    protected function ensureTileIndexIsValid(int $tileIndex): void
    {
        if ($tileIndex < 0 || $tileIndex > 24) {
            throw new RuntimeException('Invalid tile index.');
        }
    }

    protected function ensureTileNotAlreadyRevealed(Game $game, int $tileIndex): void
    {
        $alreadyRevealed = $game->tiles->contains(
            fn (GameTile $tile) => $tile->tile_index === $tileIndex
        );

        if ($alreadyRevealed) {
            throw new RuntimeException('This tile has already been revealed.');
        }
    }

    protected function chooseRevealPrize(Game $game): Prize
    {
        /** @var \Illuminate\Support\Collection<int,int> $counts */
        $counts = $game->tiles
            ->groupBy('prize_id')
            ->map(fn (Collection $tiles) => $tiles->count());

        // Lock the winner prize row for update to prevent overselling
        $winner = Prize::where('id', $game->winning_prize_id)->lockForUpdate()->first();

        if (! $winner) {
            throw new RuntimeException('Winning prize is missing.');
        }

        $winnerCount = (int) ($counts[$winner->id] ?? 0);
        $nextFlip = $game->flips_count + 1;
        $winningFlip = $game->winning_flip; // can be null

        // Winnable prizes (for winner logic)
        $winnablePrizes = $this->prizeSelectionService->getWinnablePrizes($game->campaign, $game->segment);
        // Playable prizes (for decoys)
        $playablePrizes = $this->prizeSelectionService->getPlayablePrizes($game->campaign, $game->segment);

        if ($playablePrizes->isEmpty()) {
            throw new RuntimeException('No playable prizes available.');
        }

        $winnerAvailable = $winnablePrizes->contains('id', $winner->id);

        // Never allow non-winning prizes to become 3rd match.
        $decoys = $playablePrizes->filter(function (Prize $prize) use ($counts, $winner) {
            $count = (int) ($counts[$prize->id] ?? 0);

            return $prize->id !== $winner->id && $count < 2;
        })->values();

        // If winning_flip is null, never allow 3rd match for winner (user will always lose)
        if (is_null($winningFlip)) {
            if ($winnerCount < 2 && $decoys->isNotEmpty()) {
                return $decoys->random();
            }
            if ($decoys->isEmpty()) {
                return $winner;
            }

            return $decoys->random();
        }

        // Only allow 3rd match of winner on the randomly chosen winning flip
        if ($winnerAvailable && $winnerCount >= 2 && $nextFlip == $winningFlip) {
            return $winner;
        }

        // Before 3rd match, prefer decoys over the winner to preserve suspense.
        if ($winnerCount < 2 && $decoys->isNotEmpty()) {
            return $decoys->random();
        }

        // If no decoys left, fallback to any playable prize (should not allow win if winner not available)
        if ($decoys->isEmpty()) {
            // If only winner left and can't match 3rd, just pick winner (won't allow win)
            return $winner;
        }

        return $decoys->random();
    }

    protected function finishAsWin(Game $game, Prize $prize): array
    {
        // Lock the prize row for update to prevent overselling
        $lockedPrize = Prize::where('id', $prize->id)->lockForUpdate()->first();

        if (! $this->prizeSelectionService->hasRemainingDailyCapacity($lockedPrize)) {
            throw new RuntimeException('Prize daily limit has been reached.');
        }

        $game->update([
            'prize_id' => $prize->id,
            'result' => 'won',
            'finished_at' => now(),
        ]);

        return [
            'tileImage' => $prize->image,
            'message' => 'You won a prize!',
        ];
    }

    protected function finishAsLoss(Game $game, Prize $lastPrize): array
    {
        $game->update([
            'result' => 'lost',
            'finished_at' => now(),
        ]);

        return [
            'tileImage' => $lastPrize->image,
            'message' => 'You lost!',
        ];
    }
}
