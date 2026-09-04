<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodoAcademico extends Model
{
    protected $table = 'periodos_academicos';

    protected $fillable = [
        'universidad_id',
        'nombre',
        'anio',
        'numero_semestre',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function universidad()
    {
        return $this->belongsTo(Universidad::class);
    }

    public function asignaturasDocentes()
    {
        return $this->hasMany(AsignaturaDocente::class);
    }

    public function secciones()
    {
        return $this->hasMany(Seccion::class);
    }

    public function disponibilidadesProfesores()
    {
        return $this->hasMany(DisponibilidadProfesor::class);
    }

    public function candidatosReemplazo()
    {
        return $this->hasMany(CandidatoReemplazo::class);
    }

    public function versionesPlanificacion()
    {
        return $this->hasMany(VersionPlanificacion::class);
    }
}