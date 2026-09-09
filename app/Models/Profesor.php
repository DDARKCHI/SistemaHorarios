<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profesor extends Model
{
    protected $table = 'profesores';

    protected $fillable = [
        'universidad_id',
        'rut',
        'nombre',
        'apellido',
        'correo_institucional',
        'correo_personal',
        'tipo_contrato',
        'tipo_profesor',
        'unidad_academica',
        'estado',
    ];

    public function universidad()
    {
        return $this->belongsTo(Universidad::class);
    }

    public function disponibilidades()
    {
        return $this->hasMany(DisponibilidadProfesor::class);
    }

    public function respuestasDisponibilidad()
    {
        return $this->hasMany(RespuestaDisponibilidad::class);
    }

    public function asignaturasDocentes()
    {
        return $this->hasMany(AsignaturaDocente::class);
    }

    public function secciones()
    {
        return $this->hasMany(SeccionProfesor::class);
    }

    public function horarios()
    {
        return $this->hasMany(HorarioProfesor::class);
    }

    public function candidatosReemplazo()
    {
        return $this->hasMany(CandidatoReemplazo::class);
    }

    public function solicitudesCambioHorario()
    {
        return $this->hasMany(SolicitudCambioHorario::class);
    }
}