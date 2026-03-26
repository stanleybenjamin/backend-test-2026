<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\CampaignStateService;
use App\Services\GameplaySessionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FrontendController extends Controller
{
    public function loadCampaign(Campaign $campaign): View
    {
        $jsonConfig = '{"apiPath" : "/api/flip", "gameId" : 1}';

        return view('frontend.index', ['config' => $jsonConfig]);
    }

    public function placeholder(): View
    {
        return view('frontend.placeholder');
    }

    public function loadCampaignv2(Request $request, Campaign $campaign): View
    {
        $account = $request->query('a');
        $segment = $request->query('segment');

        $message = app(CampaignStateService::class)->messageFor($campaign);

        if ($message || ! $account || ! in_array($segment, ['low', 'med', 'high'], true)) {
            return view('frontend.index', [
                'config' => json_encode([
                    'apiPath' => '/api/flip',
                    'gameId' => null,
                    'revealedTiles' => [],
                    'message' => $message ?? 'Invalid campaign request',
                ]),
            ]);
        }

        $gameSessionService = app(GameplaySessionService::class);
        $playerToken = $gameSessionService->resolvePlayerToken($request);
        $game = $gameSessionService->findOrCreateNewGame($campaign, $account, $segment, $playerToken);

        return view('frontend.index', [
            'config' => json_encode([
                'apiPath' => '/api/flip',
                'gameId' => $game->id,
                'revealedTiles' => $game->tiles->map(fn ($tile) => [
                    'index' => $tile->tile_index,
                    'image' => $tile->prize->tile_image,
                ])->values(),
                'message' => null,
            ]),
        ]);
    }
}
