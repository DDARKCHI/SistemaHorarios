<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequerimientoActividad extends Model
{
    protected $table = 'requerimientos_actividad';

    protected $fillable = [
        'actividad_seccion_id',
        'tipo_sala_id',
        'prioridad_recurso_id',
        'tipo_requerimiento',
        'nombre_recurso',
        'cantidad',
        'capacidad_minima',
        'observaciones',
        'obligatorio',
        'estado',
    ];

    protected $casts = [
        'obligatorio' => 'boolean',
    ];

    public function actividadSeccion()
    {
        return $this->belongsTo(ActividadSeccion::class);
    }

    public function tipoSala()
    {
        return $this->belongsTo(TipoSala::class);
    }

    public function prioridadRecurso()
    {
        return $this->belongsTo(PrioridadRecurso::class);
    }
}