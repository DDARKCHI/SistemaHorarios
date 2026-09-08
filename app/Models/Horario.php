<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Horario extends Model
{
    protected $table = 'horarios';

    protected $fillable = [
        'actividad_seccion_id',
        'grupo_seccion_id',
        'version_planificacion_id',
        'dia_id',
        'bloque_horario_id',
        'observaciones',
        'estado',
    ];

    public function actividadSeccion()
    {
        return $this->belongsTo(ActividadSeccion::class);
    }

    public function grupoSeccion()
    {
        return $this->belongsTo(GrupoSeccion::class);
    }

    public function versionPlanificacion()
    {
        return $this->belongsTo(VersionPlanificacion::class);
    }

    public function dia()
    {
        return $this->belongsTo(Dia::class);
    }

    public function bloqueHorario()
    {
        return $this->belongsTo(BloqueHorario::class);
    }

    public function asignacionesSalas()
    {
        return $this->hasMany(AsignacionSala::class);
    }

    public function asignacionesLaboratorios()
    {
        return $this->hasMany(AsignacionLaboratorio::class);
    }

    public function asignacionesLaboratoriosMoviles()
    {
        return $this->hasMany(AsignacionLaboratorioMovil::class);
    }

    public function validaciones()
    {
        return $this->hasMany(ValidacionHorario::class);
    }

    public function observacionesValidacion()
    {
        return $this->hasManyThrough(
            ObservacionValidacion::class,
            ValidacionHorario::class
        );
    }
}