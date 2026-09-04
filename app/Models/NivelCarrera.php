<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NivelCarrera extends Model
{
    protected $table = 'niveles_carrera';

    protected $fillable = [
        'carrera_id',
        'numero',
        'nombre',
        'estado',
    ];

    public function carrera()
    {
        return $this->belongsTo(Carrera::class);
    }
}