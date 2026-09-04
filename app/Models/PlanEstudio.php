<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanEstudio extends Model
{
    protected $table = 'planes_estudio';

    protected $fillable = [
        'carrera_id',
        'codigo',
        'nombre',
        'version',
        'anio_inicio',
        'estado',
    ];

    public function carrera()
    {
        return $this->belongsTo(Carrera::class);
    }
}