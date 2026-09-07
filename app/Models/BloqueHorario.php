<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloqueHorario extends Model
{
    protected $table = 'bloques_horarios';

    protected $fillable = [
        'numero',
        'hora_inicio',
        'hora_fin',
        'es_almuerzo',
        'estado',
    ];

    protected $casts = [
        'es_almuerzo' => 'boolean',
    ];

    public function disponibilidadesProfesores()
    {
        return $this->hasMany(DisponibilidadProfesor::class);
    }

    public function horarios()
    {
        return $this->hasMany(Horario::class);
    }

    public function opcionesCambioHorario()
    {
        return $this->hasMany(OpcionCambioHorario::class);
    }
}