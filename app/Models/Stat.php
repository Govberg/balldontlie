<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stat extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'player_id','pts'
    ];

    /**
     * Get the player that owns the phone.
     */
    public function player()
    {
        return $this->belongsTo('App\Models\Player');
    }
}
