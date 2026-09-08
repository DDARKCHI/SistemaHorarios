<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seccion extends Model
{
    protected $table = 'secciones';

    protected $fillable = [
        'identificador_externo',
        'asignatura_id',
        'periodo_academico_id',
        'codigo',
        'nombre',
        'cupos',
        'inscritos',
        'modalidad',
        'categoria',
        'tipo_dictacion',
        'estado',
        'estado_activo',
    ];

    protected $casts = [
        'estado_activo' => 'boolean',
    ];

    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class);
    }

    public function carreras()
    {
        return $this->belongsToMany(
            Carrera::class,
            'secciones_carreras'
        );
    }

    public function periodoAcademico()
    {
        return $this->belongsTo(PeriodoAcademico::class);
    }

    public function actividades()
    {
        return $this->hasMany(ActividadSeccion::class);
    }

    public function grupos()
    {
        return $this->hasManyThrough(
            GrupoSeccion::class,
            ActividadSeccion::class,
            'seccion_id',
            'actividad_seccion_id',
            'id',
            'id'
        );
    }

    public function profesores()
    {
        return $this->hasMany(SeccionProfesor::class);
    }
}