<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrioridadRecurso extends Model
{
    protected $table = 'prioridades_recursos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'nivel',
        'estado',
    ];

    public function requerimientosActividad()
    {
        return $this->hasMany(RequerimientoActividad::class);
    }
}