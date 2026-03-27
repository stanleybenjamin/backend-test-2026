<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\GameplayService;
use App\Services\GameplaySessionService;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function flip(Request $request)
    {
        try {

            $gameId = $request->integer('gameId');
            $tileIndex = $request->integer('tileIndex');
            //$playerToken = app(GameplaySessionService::class)->resolvePlayerToken($request);

            $game = Game::where('id', $gameId)
                //->where('player_token', $playerToken)
                ->whereNull('finished_at')
                ->first();

            if (! $game) {
                return response()->json(['message' => 'Game not found or not active for this user.'], 404);
            }

            $result = app(GameplayService::class)->flip($game, $tileIndex);

            return response()->json($result);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 422);
        }
    }
}
