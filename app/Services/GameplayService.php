<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameTile;
use App\Models\Prize;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GameplayService
{
    public function __construct(
        protected PrizeSelectionService $prizeSelectionService,
    ) {}

    /**
     * When a user flips we are simply just play the planned sequence of move but important caveat,
     * If the price is exhausted we guide the user into a loosing position ultimately
     */
    public function flip(Game $game, int $tileIndex): array
    {
        return DB::transaction(function () use ($game, $tileIndex) {
            $game->loadMissing(['campaign', 'tiles.prize', 'winningPrize']);

            $this->ensureGameCanBePlayed($game);
            $this->ensureTileIndexIsValid($tileIndex);
            $this->ensureTileNotAlreadyRevealed($game, $tileIndex);
            $this->ensureRevealPlanExists($game);

            $nextFlip = $game->flips_count + 1;
            $prize = $this->plannedPrizeForFlip($game, $nextFlip);

            if (! $prize) {
                throw new RuntimeException("No planned prize found for flip {$nextFlip}.");
            }

            GameTile::create([
                'game_id' => $game->id,
                'tile_index' => $tileIndex,
                'prize_id' => $prize->id,
            ]);

            $game->increment('flips_count');
            $game->refresh();

            if ($this->shouldWinNow($game, $prize)) {
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

        if ($game->flips_count >= $game->max_flips) {
            throw new RuntimeException('This game has no flips remaining.');
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

    protected function ensureRevealPlanExists(Game $game): void
    {
        if (! is_array($game->reveal_plan) || empty($game->reveal_plan)) {
            throw new RuntimeException('This game has no reveal plan.');
        }
    }

    /**
     * Simply pick the sequence as preplanned, we only don't record it for a win when price is exhausted
     *
     * @return Prize|\Illuminate\Database\Eloquent\Collection<int, Prize>|\stdClass|null
     */
    protected function plannedPrizeForFlip(Game $game, int $flipNumber): ?Prize
    {
        $prizeId = $game->reveal_plan[$flipNumber - 1] ?? null;

        if (! $prizeId) {
            return null;
        }

        return Prize::query()->find($prizeId);
    }

    protected function shouldWinNow(Game $game, Prize $prize): bool
    {
        if (is_null($game->winning_prize_id) || is_null($game->winning_flip)) {
            return false;
        }

        if ((int) $game->winning_flip !== (int) $game->flips_count) {
            return false;
        }

        if ((int) $game->winning_prize_id !== (int) $prize->id) {
            return false;
        }

        return true;
    }

    /**
     * Finish the game as a win, updating the game and prize state accordingly.
     * If the prize is exhausted, the game is finished as a loss instead.
     *
     * This method assumes it's being called within a transaction and
     * that the game and prize are properly locked for update to prevent race conditions.
     *
     * @return array|array{message: string, tileImage: mixed}
     */
    protected function finishAsWin(Game $game, Prize $prize): array
    {
        $lockedPrize = Prize::query()
            ->whereKey($prize->id)
            ->addTodaysWins()
            ->lockForUpdate()
            ->first();

        if (! $lockedPrize || ! $this->prizeSelectionService->hasRemainingDailyCapacity($lockedPrize)) {
            return $this->finishAsLoss($game, $prize);
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
