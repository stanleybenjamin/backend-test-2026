<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\CampaignStateService;
use App\Services\GameplaySessionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FrontendController extends Controller
{
    public function placeholder(): View
    {
        return view('frontend.placeholder');
    }

    public function loadCampaign(Request $request, Campaign $campaign): View
    {
        $segment = $request->query('segment');

        $message = app(CampaignStateService::class)->messageFor($campaign);

        if ($message || ! in_array($segment, ['low', 'med', 'high'], true)) {
            return view('frontend.index', [
                'config' => json_encode([
                    'apiPath' => '/api/flip',
                    'gameId' => null,
                    'message' => $message ?? 'Invalid campaign request',
                ]),
            ]);
        }

        $gameSessionService = app(GameplaySessionService::class);
        $playerToken = $gameSessionService->resolvePlayerToken($request);
        $game = $gameSessionService->findOrCreateNewGame($campaign, $segment, $playerToken);

        return view('frontend.index', [
            'config' => json_encode([
                'apiPath' => '/api/flip',
                'gameId' => $game->id,
                'message' => null,
            ]),
        ]);
    }
}
