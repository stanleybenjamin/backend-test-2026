<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\GamePlayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApiController extends Controller
{
    public function flip()
    {
        /**
         * This is a simplified example to demonstrate interaction with the provided frontend (FE).
         * The game objective is to collect three matching tiles to win a prize. Once three matching tiles are collected:
         *   - The game ends.
         *   - The prize is awarded, and its daily volume limit (defined in the back office) must be updated.
         *
         * Requirements:
         * - Use the database layer to store and manage all game-related data, including game state and prize counts.
         * - Cache is used here only for demonstration purposes and should be replaced with proper database storage.
         */
        $currentMove = (Cache::get(request('gameId')) ?? 0) + 1;
        Cache::put(request('gameId'), $currentMove);

        if ($currentMove >= 10) {
            Cache::forget(request('gameId'));
        }

        return [
            'tileImage' => asset('assets/'.random_int(1, 7).'.png'),
        ] + ($currentMove >= 10 ? ['message' => 'You lost!'] : []);
    }

    public function v2(Request $request)
    {
        $game = Game::findOrFail($request->integer('gameId'));

        return response()->json(
            app(GamePlayService::class)->flip($game, $request->integer('tileIndex'))
        );
    }
}
