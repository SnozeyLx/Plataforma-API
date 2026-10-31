<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Roulette extends Model
{
    protected $table = 'roulettes';
    protected $fillable = [
        'image_profile',
        'name',
        'reward'
    ];
}
