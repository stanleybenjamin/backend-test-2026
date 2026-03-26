<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameTile extends Model
{
    /** @use HasFactory<\Database\Factories\GameTileFactory> */
    use HasFactory;

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function prize()
    {
        return $this->belongsTo(Prize::class);
    }
}
