<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Points extends Model
{

protected $table =  'points';
protected $fillable = [
    'user_id',
    'points'
];
}
