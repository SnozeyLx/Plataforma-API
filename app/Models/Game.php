<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Room;
use App\Models\Bet;

class Game extends Model
{

    protected $fillable = [
        'uuid',
        'card_left',
        'card_right',
        'card_winner',
        'finished_at'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function room() {
        return $this->belongsTo(Room::class);
    }

    public function bets() {
        return $this->hasMany(Bet::class);
    }
}
