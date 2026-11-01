<?php

// app/Models/GameCanceled.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameCanceled extends Model
{
    protected $table = 'games_canceled';

    protected $fillable = [
        'game_id', 'room_id', 'canceled_by', 'payload'
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
