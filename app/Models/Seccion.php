<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seccion extends Model
{
    protected $table = 'secciones';

    protected $fillable = [
        'asignatura_id',
        'carrera_id',
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

    public function carrera()
    {
        return $this->belongsTo(Carrera::class);
    }

    public function periodoAcademico()
    {
        return $this->belongsTo(PeriodoAcademico::class);
    }

    public function actividades()
    {
        return $this->hasMany(ActividadSeccion::class);
    }

    public function profesores()
    {
        return $this->hasMany(SeccionProfesor::class);
    }
}