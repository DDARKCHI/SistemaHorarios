<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidacionHorario extends Model
{
    protected $table = 'validaciones_horario';

    protected $fillable = [
        'version_planificacion_id',
        'horario_id',
        'tipo_validacion',
        'resultado',
        'detalle',
        'nivel',
    ];

    public function versionPlanificacion()
    {
        return $this->belongsTo(VersionPlanificacion::class);
    }

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function observaciones()
    {
        return $this->hasMany(ObservacionValidacion::class);
    }
}