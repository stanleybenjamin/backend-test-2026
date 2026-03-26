<?php

namespace Database\Seeders;

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
        Game::factory()->count(10000)->afterMaking(function (Game $game) {
            $prize = Prize::where('segment', $game->segment)->inRandomOrder()->firstOrFail();
            $game->prize_id = $prize->id;
            $game->campaign_id = $prize->campaign_id;
        })->create();
    }
}
