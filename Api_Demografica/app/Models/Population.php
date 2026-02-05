<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Population extends Model
{
    use HasFactory;

    protected $guarded = [];
    public $timestamps = false;
    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    public function island()
    {
        return $this->belongsTo(Island::class);
    }
}