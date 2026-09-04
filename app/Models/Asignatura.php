<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asignatura extends Model
{
    protected $table = 'asignaturas';

    protected $fillable = [
        'universidad_id',
        'codigo',
        'nombre',
        'creditos',
        'horas_catedra',
        'horas_laboratorio',
        'horas_ayudantia',
        'horas_taller',
        'horas_clinica',
        'horas_practica',
        'horas_otros',
        'modalidad',
        'categoria',
        'tipo_dictacion',
        'es_libre_eleccion',
        'ramo_terminal',
        'estado',
    ];

    protected $casts = [
        'es_libre_eleccion' => 'boolean',
        'ramo_terminal' => 'boolean',
    ];

    public function universidad()
    {
        return $this->belongsTo(Universidad::class);
    }

    public function secciones()
    {
        return $this->hasMany(Seccion::class);
    }

    public function asignaturasDocentes()
    {
        return $this->hasMany(AsignaturaDocente::class);
    }

    public function candidatosReemplazo()
    {
        return $this->hasMany(CandidatoReemplazo::class);
    }
}