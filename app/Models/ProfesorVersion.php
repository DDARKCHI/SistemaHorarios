<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfesorVersion extends Model
{
    protected $table = 'profesores_versiones';

    protected $fillable = [
        'version_planificacion_id',
        'profesor_id',
        'rut',
        'nombre',
        'apellido',
        'correo_institucional',
        'correo_personal',
        'tipo_contrato',
        'tipo_profesor',
        'unidad_academica',
    ];

    public function versionPlanificacion()
    {
        return $this->belongsTo(VersionPlanificacion::class);
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class);
    }
}