<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Game;
use App\Models\Prize;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Game::truncate();
        Schema::enableForeignKeyConstraints();
        // get all prize
        Game::factory()->for(Campaign::factory())->count(10000)->afterMaking(function (Game $game) {
            $game->prize_id = Prize::where('campaign_id', $game->campaign_id)->inRandomOrder()->first()->id;
        })->create();
    }
}
