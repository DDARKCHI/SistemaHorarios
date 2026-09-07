<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActividadSeccion extends Model
{
    protected $table = 'actividades_seccion';

    protected $fillable = [
        'seccion_id',
        'tipo',
        'cantidad_horas',
        'cantidad_estudiantes',
        'modalidad',
        'observaciones',
        'estado',
    ];

    public function seccion()
    {
        return $this->belongsTo(Seccion::class);
    }

    public function requerimientos()
    {
        return $this->hasMany(RequerimientoActividad::class);
    }

    public function horarios()
    {
        return $this->hasMany(Horario::class);
    }
}