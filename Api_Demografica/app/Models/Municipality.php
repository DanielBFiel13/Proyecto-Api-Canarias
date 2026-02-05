<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    use HasFactory;

    protected $guarded = [];
    public $timestamps = false;
    // Un municipio pertenece a una isla
    public function island()
    {
        return $this->belongsTo(Island::class);
    }

    // Un municipio tiene muchos datos de población
    public function populations()
    {
        return $this->hasMany(Population::class);
    }
}