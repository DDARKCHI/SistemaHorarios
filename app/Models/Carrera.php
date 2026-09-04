<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carrera extends Model
{
    protected $table = 'carreras';

    protected $fillable = [
        'universidad_id',
        'facultad_instituto_id',
        'sede_id',
        'codigo',
        'nombre',
        'regimen',
        'tipo_estudio',
        'estado',
    ];

    public function universidad()
    {
        return $this->belongsTo(Universidad::class);
    }

    public function facultadInstituto()
    {
        return $this->belongsTo(FacultadInstituto::class);
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function planesEstudio()
    {
        return $this->hasMany(PlanEstudio::class);
    }

    public function nivelesCarrera()
    {
        return $this->hasMany(NivelCarrera::class);
    }

    public function secciones()
    {
        return $this->hasMany(Seccion::class);
    }
}