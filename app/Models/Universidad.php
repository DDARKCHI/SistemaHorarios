<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Universidad extends Model
{
    protected $table = 'universidades';

    protected $fillable = [
        'nombre',
        'sigla',
        'estado',
    ];

    public function facultadesInstitutos()
    {
        return $this->hasMany(FacultadInstituto::class);
    }

    public function sedes()
    {
        return $this->hasMany(Sede::class);
    }

    public function carreras()
    {
        return $this->hasMany(Carrera::class);
    }

    public function periodosAcademicos()
    {
        return $this->hasMany(PeriodoAcademico::class);
    }

    public function asignaturas()
    {
        return $this->hasMany(Asignatura::class);
    }

    public function profesores()
    {
        return $this->hasMany(Profesor::class);
    }

    public function salas()
    {
        return $this->hasMany(Sala::class);
    }

    public function laboratoriosMoviles()
    {
        return $this->hasMany(LaboratorioMovil::class);
    }

    public function equipamientos()
    {
        return $this->hasMany(Equipamiento::class);
    }

    public function configuracionInstitucion()
    {
        return $this->hasOne(ConfiguracionInstitucion::class);
    }

    public function temasVisuales()
    {
        return $this->hasMany(TemaVisual::class);
    }
}