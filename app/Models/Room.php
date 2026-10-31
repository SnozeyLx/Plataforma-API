<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'rooms';

    protected $fillable = [
        'user_id',
        'live_url',
        'finished_at'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
