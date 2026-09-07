<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ObservacionValidacion extends Model
{
    protected $table = 'observaciones_validacion';

    protected $fillable = [
        'validacion_horario_id',
        'horario_id',
        'tipo',
        'nivel',
        'mensaje',
        'resuelta',
        'fecha_resolucion',
    ];

    protected $casts = [
        'resuelta' => 'boolean',
        'fecha_resolucion' => 'datetime',
    ];

    public function validacionHorario()
    {
        return $this->belongsTo(ValidacionHorario::class);
    }

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }
}