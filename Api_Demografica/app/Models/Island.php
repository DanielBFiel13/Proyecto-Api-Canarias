<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Island extends Model
{
    use HasFactory;

    protected $guarded = [];
    public $timestamps = false;

    // Una isla tiene muchos municipios
    public function municipalities()
    {
        return $this->hasMany(Municipality::class);
    }

    // Una isla tiene muchos registros de población
    public function populations()
    {
        return $this->hasMany(Population::class);
    }
}