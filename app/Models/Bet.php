<?php

namespace App\Models;

use App\Enums\BetStatus;
use Illuminate\Database\Eloquent\Model;

class Bet extends Model
{
    protected $table = 'bets';

    protected $casts = [
        'value' => 'float',
        'status' => BetStatus::class,
    ];

    protected $fillable = [
        'room_id',
        'game_id',
        'user_id',
        'card_id',
        'value',
    ];

    public function game() {
        return $this->belongsTo(Game::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

}
