<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BetSnapshot extends Model
{
    protected $table = 'bet_snapshots';

    protected $fillable = [
        'bet_id',
        'room_id',
        'game_id',
        'user_id',
        'card_id',
        'bet_value',
        'balance_before',
        'balance_after',
    ];

    protected $casts = [
        'bet_value' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function bet()
    {
        return $this->belongsTo(Bet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}
